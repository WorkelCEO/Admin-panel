<?php

namespace App\Services;

use App\DTOs\ApiResponse;
use App\Exceptions\ApiConnectionException;
use App\Exceptions\ApiException;
use App\Exceptions\ApiNotFoundException;
use App\Exceptions\ApiRateLimitException;
use App\Exceptions\ApiTimeoutException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * Base API Service with retry logic, circuit breaker, and caching
 */
abstract class BaseApiService
{
    protected string $baseUrl;
    protected CircuitBreaker $circuitBreaker;
    protected int $maxRetries = 3;
    protected array $retryDelays = [1, 2, 4]; // seconds
    protected int $timeout = 10;
    protected int $retryTimeout = 30;

    public function __construct()
    {
        $this->baseUrl = $this->getBaseUrl();
        // Don't store token as instance property - get it fresh on each request
        $this->circuitBreaker = new CircuitBreaker($this->getServiceName());
    }

    /**
     * Get the base URL for the API
     */
    abstract protected function getBaseUrl(): string;

    /**
     * Get the authentication token
     */
    abstract protected function getToken(): ?string;

    /**
     * Get the service name for circuit breaker
     */
    abstract protected function getServiceName(): string;

    /**
     * Handle unauthorized (401) response
     * Can be overridden by child classes to implement token refresh logic
     * 
     * @param string $endpoint The endpoint that returned 401
     * @param array $responseData The response data from the API
     * @return bool True if the issue was resolved (e.g., token refreshed), false otherwise
     */
    protected function handleUnauthorized(string $endpoint, array $responseData = []): bool
    {
        // Default implementation: cannot resolve unauthorized
        // Child classes can override to attempt token refresh
        return false;
    }

    /**
     * Get HTTP client with authentication headers
     * Can be overridden by child classes
     */
    protected function client(): PendingRequest
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        // Get fresh token on each request (don't rely on constructor token)
        $token = $this->getToken();
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $client = Http::withHeaders($headers)->timeout($this->timeout);
        
        // Disable SSL verification if configured (development only)
        $verifySsl = config('services.' . $this->getServiceName() . '.verify_ssl', true);
        $envVerifySsl = env(strtoupper($this->getServiceName()) . '_VERIFY_SSL', null);
        
        // Handle boolean and string values for SSL verification
        if ($verifySsl === false || $verifySsl === 'false' || 
            ($envVerifySsl !== null && ($envVerifySsl === false || $envVerifySsl === 'false'))) {
            $client = $client->withoutVerifying();
            Log::debug("SSL verification disabled for {$this->getServiceName()}", [
                'verify_ssl_config' => $verifySsl,
                'verify_ssl_env' => $envVerifySsl,
            ]);
        }

        return $client;
    }

    /**
     * Execute GET request with retry logic, circuit breaker, and caching
     */
    protected function get(string $endpoint, array $params = [], ?int $cacheTtl = null): ApiResponse
    {
        $startTime = microtime(true);
        $fullUrl = $this->baseUrl . $endpoint;
        
        // Get fresh token for logging
        $token = $this->getToken();
        
        // Log API request
        Log::info("API Request: GET {$fullUrl}", [
            'service' => $this->getServiceName(),
            'endpoint' => $endpoint,
            'params' => $this->sanitizeParams($params),
            'cache_enabled' => $cacheTtl !== null,
            'has_token' => !empty($token),
            'token_preview' => $token ? substr($token, 0, 20) . '...' : null,
            'base_url' => $this->baseUrl,
        ]);
        
        // Check if token is missing and this is an authenticated endpoint
        if (empty($token) && !str_contains($endpoint, '/auth/login') && !str_contains($endpoint, '/auth/refresh')) {
            // Try to get token from Session/Cache directly for logging
            $sessionToken = Session::has('admin_api_token');
            $cacheToken = Cache::has('admin_api_token');
            
            Log::warning("API Request: GET {$fullUrl} - No authentication token found", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'requires_auth' => true,
                'session_has_token' => $sessionToken,
                'cache_has_token' => $cacheToken,
                'token_from_getToken' => empty($token) ? 'NULL' : 'SET',
            ]);
            
            // Try to handle missing token (e.g., retrieve from storage or refresh)
            if ($this->handleUnauthorized($endpoint, [])) {
                // Token was retrieved/refreshed, get it again
                $token = $this->getToken();
                if (!empty($token)) {
                    Log::info("API Request: GET {$fullUrl} - Token retrieved, proceeding with request", [
                        'service' => $this->getServiceName(),
                        'endpoint' => $endpoint,
                    ]);
                }
            } else {
                // Could not retrieve token, throw exception
                throw new \App\Exceptions\ApiException(
                    "Authentication token is missing. Please log in again.",
                    401,
                    null,
                    $endpoint,
                    ['status' => 401, 'requires_auth' => true, 'token_missing' => true]
                );
            }
        }

        // Check circuit breaker
        if (!$this->circuitBreaker->allowsRequest()) {
            Log::warning("Circuit breaker is open for {$this->getServiceName()}", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'full_url' => $fullUrl,
            ]);

            throw new ApiConnectionException(
                "Service temporarily unavailable. Circuit breaker is open.",
                $endpoint
            );
        }

        // Check cache for GET requests
        if ($cacheTtl !== null) {
            $cacheKey = $this->getCacheKey($endpoint, $params);
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                Log::debug("API Cache Hit: GET {$fullUrl}", [
                    'service' => $this->getServiceName(),
                    'endpoint' => $endpoint,
                    'cache_key' => $cacheKey,
                    'duration_ms' => $duration,
                ]);
                return $cached;
            }
        }

        $lastException = null;

        // Retry loop
        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                if ($attempt > 0) {
                    $delay = $this->retryDelays[$attempt - 1] ?? end($this->retryDelays);
                    Log::warning("API Retry: GET {$fullUrl}", [
                        'service' => $this->getServiceName(),
                        'endpoint' => $endpoint,
                        'attempt' => $attempt + 1,
                        'max_attempts' => $this->maxRetries + 1,
                        'delay_seconds' => $delay,
                    ]);
                    sleep($delay);
                }

                $response = $this->client()
                    ->timeout($attempt > 0 ? $this->retryTimeout : $this->timeout)
                    ->get($fullUrl, $params);

                $duration = round((microtime(true) - $startTime) * 1000, 2);
                $responseSize = strlen($response->body());

                // Handle different response statuses
                if ($response->successful()) {
                    $this->circuitBreaker->recordSuccess();
                    
                    $responseJson = $response->json();
                    $responseMessage = $responseJson['message'] ?? null;
                    
                    // Extract data according to API response structure:
                    // { success: true, data: { current_page, data: [...], total, ... } } for paginated
                    // { success: true, data: {...} } for non-paginated
                    $responseData = $responseJson['data'] ?? $responseJson;
                    
                    // For paginated responses, the entire pagination object is in 'data'
                    // For non-paginated responses, 'data' contains the actual response data
                    $apiResponse = ApiResponse::success(
                        $responseData,
                        [], // API doesn't use 'meta' field - pagination is in 'data' object
                        $responseMessage
                    );

                    // Log successful API response
                    Log::info("API Response: GET {$fullUrl}", [
                        'service' => $this->getServiceName(),
                        'endpoint' => $endpoint,
                        'status_code' => $response->status(),
                        'duration_ms' => $duration,
                        'response_size_bytes' => $responseSize,
                        'attempt' => $attempt + 1,
                        'has_data' => !empty($responseData),
                        'is_paginated' => isset($responseData['data']) && isset($responseData['current_page']),
                        'data_count' => isset($responseData['data']) && is_array($responseData['data']) 
                            ? count($responseData['data']) 
                            : (is_array($responseData) ? count($responseData) : 0),
                    ]);

                    // Cache successful response
                    if ($cacheTtl !== null) {
                        $cacheKey = $this->getCacheKey($endpoint, $params);
                        Cache::put($cacheKey, $apiResponse, $cacheTtl);
                        Log::debug("API Response Cached: GET {$fullUrl}", [
                            'service' => $this->getServiceName(),
                            'cache_key' => $cacheKey,
                            'cache_ttl_seconds' => $cacheTtl,
                        ]);
                    }

                    return $apiResponse;
                }

                $duration = round((microtime(true) - $startTime) * 1000, 2);
                $statusCode = $response->status();
                $responseBody = $response->body();
                $responseData = [];
                
                // Try to parse response JSON if possible
                try {
                    $responseData = $response->json() ?? [];
                } catch (\Exception $e) {
                    // If JSON parsing fails, use empty array
                }

                // Handle specific error statuses
                if ($statusCode === 401) {
                    // 401 Unauthenticated - token might be expired or missing
                    $this->circuitBreaker->recordSuccess(); // 401 is not a service failure, it's an auth issue
                    
                    $token = $this->getToken();
                    Log::warning("API Response: GET {$fullUrl} - Unauthenticated (401)", [
                        'service' => $this->getServiceName(),
                        'endpoint' => $endpoint,
                        'status_code' => 401,
                        'duration_ms' => $duration,
                        'attempt' => $attempt + 1,
                        'has_token' => !empty($token),
                        'token_preview' => $token ? substr($token, 0, 20) . '...' : null,
                        'response_message' => $responseData['message'] ?? 'Unauthenticated',
                        'response_body' => substr($responseBody, 0, 500),
                    ]);
                    
                    // Try to handle unauthorized (e.g., refresh token)
                    // Only attempt on first try to avoid infinite loops
                    if ($attempt === 0 && $this->handleUnauthorized($endpoint, $responseData)) {
                        Log::info("API Response: GET {$fullUrl} - Unauthorized handled, retrying request", [
                            'service' => $this->getServiceName(),
                            'endpoint' => $endpoint,
                            'attempt' => $attempt + 1,
                        ]);
                        // Retry the request with new token
                        continue;
                    }
                    
                    throw new \App\Exceptions\ApiException(
                        "Unauthenticated. Please log in again.",
                        $statusCode,
                        null,
                        $endpoint,
                        ['status' => 401, 'body' => $responseBody, 'requires_auth' => true]
                    );
                }
                
                if ($statusCode === 404) {
                    $this->circuitBreaker->recordSuccess(); // 404 is not a service failure
                    
                    Log::warning("API Response: GET {$fullUrl} - Not Found", [
                        'service' => $this->getServiceName(),
                        'endpoint' => $endpoint,
                        'status_code' => 404,
                        'duration_ms' => $duration,
                        'attempt' => $attempt + 1,
                    ]);
                    
                    throw new ApiNotFoundException(
                        "Resource not found",
                        $endpoint,
                        ['status' => 404, 'body' => $responseBody]
                    );
                }

                if ($statusCode === 429) {
                    $retryAfterHeader = $response->header('Retry-After');
                    $retryAfter = $retryAfterHeader ? (int) $retryAfterHeader : 60;
                    $this->circuitBreaker->recordFailure();
                    
                    Log::warning("API Response: GET {$fullUrl} - Rate Limited", [
                        'service' => $this->getServiceName(),
                        'endpoint' => $endpoint,
                        'status_code' => 429,
                        'duration_ms' => $duration,
                        'retry_after_seconds' => $retryAfter,
                        'attempt' => $attempt + 1,
                    ]);
                    
                    throw new ApiRateLimitException(
                        "Rate limit exceeded",
                        $endpoint,
                        $retryAfter,
                        ['status' => 429, 'body' => $responseBody]
                    );
                }

                // Other errors
                $this->circuitBreaker->recordFailure();
                
                Log::error("API Response: GET {$fullUrl} - Error", [
                    'service' => $this->getServiceName(),
                    'endpoint' => $endpoint,
                    'status_code' => $statusCode,
                    'duration_ms' => $duration,
                    'attempt' => $attempt + 1,
                    'response_preview' => substr($responseBody, 0, 500),
                ]);
                
                throw new ApiException(
                    "API request failed with status {$statusCode}",
                    $statusCode,
                    null,
                    $endpoint,
                    ['status' => $statusCode, 'body' => $responseBody]
                );

            } catch (ApiException $e) {
                // Log API exceptions before re-throwing
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                Log::error("API Exception: GET {$fullUrl}", [
                    'service' => $this->getServiceName(),
                    'endpoint' => $endpoint,
                    'exception_type' => get_class($e),
                    'message' => $e->getMessage(),
                    'duration_ms' => $duration,
                    'attempt' => $attempt + 1,
                    'context' => $e->getContext(),
                ]);
                
                // Re-throw API exceptions immediately
                throw $e;
            } catch (RequestException $e) {
                $lastException = $e;
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                
                // Check if it's a timeout
                if (str_contains(strtolower($e->getMessage()), 'timeout') || 
                    str_contains(strtolower($e->getMessage()), 'timed out')) {
                    if ($attempt === $this->maxRetries) {
                        $this->circuitBreaker->recordFailure();
                        
                        Log::error("API Timeout: GET {$fullUrl}", [
                            'service' => $this->getServiceName(),
                            'endpoint' => $endpoint,
                            'message' => $e->getMessage(),
                            'duration_ms' => $duration,
                            'attempts' => $attempt + 1,
                            'max_attempts' => $this->maxRetries + 1,
                        ]);
                        
                        throw new ApiTimeoutException(
                            "Request timed out after {$this->maxRetries} attempts",
                            $endpoint,
                            ['attempts' => $attempt + 1],
                            $e
                        );
                    }
                    continue;
                }

                // Connection errors
                if ($attempt === $this->maxRetries) {
                    $this->circuitBreaker->recordFailure();
                    
                    Log::error("API Connection Error: GET {$fullUrl}", [
                        'service' => $this->getServiceName(),
                        'endpoint' => $endpoint,
                        'message' => $e->getMessage(),
                        'duration_ms' => $duration,
                        'attempts' => $attempt + 1,
                        'max_attempts' => $this->maxRetries + 1,
                    ]);
                    
                    throw new ApiConnectionException(
                        "Failed to connect to API after {$this->maxRetries} attempts: " . $e->getMessage(),
                        $endpoint,
                        ['attempts' => $attempt + 1],
                        $e
                    );
                }
            } catch (\Exception $e) {
                $lastException = $e;
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                
                if ($attempt === $this->maxRetries) {
                    $this->circuitBreaker->recordFailure();
                    
                    Log::error("API Unexpected Error: GET {$fullUrl}", [
                        'service' => $this->getServiceName(),
                        'endpoint' => $endpoint,
                        'exception_type' => get_class($e),
                        'message' => $e->getMessage(),
                        'duration_ms' => $duration,
                        'attempts' => $attempt + 1,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    throw new ApiException(
                        "Unexpected error: " . $e->getMessage(),
                        0,
                        $e,
                        $endpoint
                    );
                }
            }
        }

        // Should never reach here, but just in case
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $this->circuitBreaker->recordFailure();
        
        Log::error("API Request Failed: GET {$fullUrl} - All retries exhausted", [
            'service' => $this->getServiceName(),
            'endpoint' => $endpoint,
            'duration_ms' => $duration,
            'max_attempts' => $this->maxRetries + 1,
            'last_exception' => $lastException ? get_class($lastException) : null,
        ]);
        
        throw new ApiException(
            "Request failed after all retry attempts",
            0,
            $lastException,
            $endpoint
        );
    }

    /**
     * Execute POST request
     */
    protected function post(string $endpoint, array $data = []): ApiResponse
    {
        $startTime = microtime(true);
        $fullUrl = $this->baseUrl . $endpoint;
        
        // Log API request
        Log::info("API Request: POST {$fullUrl}", [
            'service' => $this->getServiceName(),
            'endpoint' => $endpoint,
            'data_keys' => array_keys($data),
            'data_size' => strlen(json_encode($data)),
        ]);

        if (!$this->circuitBreaker->allowsRequest()) {
            Log::warning("Circuit breaker is open for POST {$fullUrl}", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
            ]);
            
            throw new ApiConnectionException(
                "Service temporarily unavailable. Circuit breaker is open.",
                $endpoint
            );
        }

        try {
            $response = $this->client()->post($fullUrl, $data);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $statusCode = $response->status();
            $responseSize = strlen($response->body());

            if ($response->successful()) {
                $this->circuitBreaker->recordSuccess();
                $responseJson = $response->json();
                $responseMessage = $responseJson['message'] ?? null;
                
                // Extract data according to API response structure
                // { success: true, message: "...", data: {...} }
                $responseData = $responseJson['data'] ?? $responseJson;
                
                // Log successful API response
                Log::info("API Response: POST {$fullUrl}", [
                    'service' => $this->getServiceName(),
                    'endpoint' => $endpoint,
                    'status_code' => $statusCode,
                    'duration_ms' => $duration,
                    'response_size_bytes' => $responseSize,
                    'has_data' => !empty($responseData),
                ]);
                
                return ApiResponse::success(
                    $responseData,
                    [], // API doesn't use 'meta' field
                    $responseMessage
                );
            }

            // Handle 401 Unauthorized
            if ($statusCode === 401) {
                $this->circuitBreaker->recordSuccess(); // 401 is not a service failure
                
                $responseData = [];
                try {
                    $responseData = $response->json() ?? [];
                } catch (\Exception $e) {
                    // If JSON parsing fails, use empty array
                }
                
                $token = $this->getToken();
                Log::warning("API Response: POST {$fullUrl} - Unauthenticated (401)", [
                    'service' => $this->getServiceName(),
                    'endpoint' => $endpoint,
                    'status_code' => 401,
                    'duration_ms' => $duration,
                    'has_token' => !empty($token),
                    'token_preview' => $token ? substr($token, 0, 20) . '...' : null,
                    'response_message' => $responseData['message'] ?? 'Unauthenticated',
                ]);
                
                // Try to handle unauthorized (e.g., refresh token)
                if ($this->handleUnauthorized($endpoint, $responseData)) {
                    Log::info("API Response: POST {$fullUrl} - Unauthorized handled, retrying request", [
                        'service' => $this->getServiceName(),
                        'endpoint' => $endpoint,
                    ]);
                    // Retry the request with new token
                    return $this->post($endpoint, $data);
                }
                
                throw new ApiException(
                    "Unauthenticated. Please log in again.",
                    $statusCode,
                    null,
                    $endpoint,
                    ['status' => 401, 'body' => $response->body(), 'requires_auth' => true]
                );
            }

            $this->circuitBreaker->recordFailure();
            
            Log::error("API Response: POST {$fullUrl} - Error", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'duration_ms' => $duration,
                'response_preview' => substr($response->body(), 0, 500),
            ]);
            
            throw new ApiException(
                "POST request failed with status {$statusCode}",
                $statusCode,
                null,
                $endpoint,
                ['status' => $statusCode, 'body' => $response->body()]
            );
        } catch (RequestException $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->circuitBreaker->recordFailure();
            
            Log::error("API Connection Error: POST {$fullUrl}", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);
            
            throw new ApiConnectionException(
                "Failed to execute POST request: " . $e->getMessage(),
                $endpoint,
                null,
                $e
            );
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->circuitBreaker->recordFailure();
            
            Log::error("API Unexpected Error: POST {$fullUrl}", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'exception_type' => get_class($e),
                'message' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);
            
            throw $e;
        }
    }

    /**
     * Execute DELETE request
     */
    protected function delete(string $endpoint): bool
    {
        $startTime = microtime(true);
        $fullUrl = $this->baseUrl . $endpoint;
        
        // Log API request
        Log::info("API Request: DELETE {$fullUrl}", [
            'service' => $this->getServiceName(),
            'endpoint' => $endpoint,
        ]);

        if (!$this->circuitBreaker->allowsRequest()) {
            Log::warning("Circuit breaker is open for DELETE {$fullUrl}", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
            ]);
            
            throw new ApiConnectionException(
                "Service temporarily unavailable. Circuit breaker is open.",
                $endpoint
            );
        }

        try {
            $response = $this->client()->delete($fullUrl);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $statusCode = $response->status();

            if ($response->successful()) {
                $this->circuitBreaker->recordSuccess();
                
                // Invalidate cache for this endpoint
                $this->invalidateCache($endpoint);
                
                // Log successful API response
                Log::info("API Response: DELETE {$fullUrl}", [
                    'service' => $this->getServiceName(),
                    'endpoint' => $endpoint,
                    'status_code' => $statusCode,
                    'duration_ms' => $duration,
                    'cache_invalidated' => true,
                ]);
                
                return true;
            }

            $this->circuitBreaker->recordFailure();
            
            Log::warning("API Response: DELETE {$fullUrl} - Failed", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'duration_ms' => $duration,
            ]);
            
            return false;
        } catch (RequestException $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->circuitBreaker->recordFailure();
            
            Log::error("API Connection Error: DELETE {$fullUrl}", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);
            
            throw new ApiConnectionException(
                "Failed to execute DELETE request: " . $e->getMessage(),
                $endpoint,
                null,
                $e
            );
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->circuitBreaker->recordFailure();
            
            Log::error("API Unexpected Error: DELETE {$fullUrl}", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'exception_type' => get_class($e),
                'message' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);
            
            throw $e;
        }
    }

    /**
     * Execute PUT request
     */
    protected function put(string $endpoint, array $data = []): ApiResponse
    {
        $startTime = microtime(true);
        $fullUrl = $this->baseUrl . $endpoint;
        
        // Log API request
        Log::info("API Request: PUT {$fullUrl}", [
            'service' => $this->getServiceName(),
            'endpoint' => $endpoint,
            'data_keys' => array_keys($data),
            'data_size' => strlen(json_encode($data)),
        ]);

        if (!$this->circuitBreaker->allowsRequest()) {
            Log::warning("Circuit breaker is open for PUT {$fullUrl}", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
            ]);
            
            throw new ApiConnectionException(
                "Service temporarily unavailable. Circuit breaker is open.",
                $endpoint
            );
        }

        try {
            $response = $this->client()->put($fullUrl, $data);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $statusCode = $response->status();
            $responseSize = strlen($response->body());

            if ($response->successful()) {
                $this->circuitBreaker->recordSuccess();
                $responseJson = $response->json();
                $responseMessage = $responseJson['message'] ?? null;
                
                // Extract data according to API response structure
                // { success: true, message: "...", data: {...} }
                $responseData = $responseJson['data'] ?? $responseJson;
                
                // Log successful API response
                Log::info("API Response: PUT {$fullUrl}", [
                    'service' => $this->getServiceName(),
                    'endpoint' => $endpoint,
                    'status_code' => $statusCode,
                    'duration_ms' => $duration,
                    'response_size_bytes' => $responseSize,
                    'has_data' => !empty($responseData),
                ]);
                
                return ApiResponse::success(
                    $responseData,
                    [], // API doesn't use 'meta' field
                    $responseMessage
                );
            }

            // Handle 401 Unauthorized
            if ($statusCode === 401) {
                $this->circuitBreaker->recordSuccess(); // 401 is not a service failure
                
                $responseData = [];
                try {
                    $responseData = $response->json() ?? [];
                } catch (\Exception $e) {
                    // If JSON parsing fails, use empty array
                }
                
                $token = $this->getToken();
                Log::warning("API Response: PUT {$fullUrl} - Unauthenticated (401)", [
                    'service' => $this->getServiceName(),
                    'endpoint' => $endpoint,
                    'status_code' => 401,
                    'duration_ms' => $duration,
                    'has_token' => !empty($token),
                    'token_preview' => $token ? substr($token, 0, 20) . '...' : null,
                    'response_message' => $responseData['message'] ?? 'Unauthenticated',
                ]);
                
                // Try to handle unauthorized (e.g., refresh token)
                if ($this->handleUnauthorized($endpoint, $responseData)) {
                    Log::info("API Response: PUT {$fullUrl} - Unauthorized handled, retrying request", [
                        'service' => $this->getServiceName(),
                        'endpoint' => $endpoint,
                    ]);
                    // Retry the request with new token
                    return $this->put($endpoint, $data);
                }
                
                throw new ApiException(
                    "Unauthenticated. Please log in again.",
                    $statusCode,
                    null,
                    $endpoint,
                    ['status' => 401, 'body' => $response->body(), 'requires_auth' => true]
                );
            }

            $this->circuitBreaker->recordFailure();
            
            Log::error("API Response: PUT {$fullUrl} - Error", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'duration_ms' => $duration,
                'response_preview' => substr($response->body(), 0, 500),
            ]);
            
            throw new ApiException(
                "PUT request failed with status {$statusCode}",
                $statusCode,
                null,
                $endpoint,
                ['status' => $statusCode, 'body' => $response->body()]
            );
        } catch (RequestException $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->circuitBreaker->recordFailure();
            
            Log::error("API Connection Error: PUT {$fullUrl}", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);
            
            throw new ApiConnectionException(
                "Failed to execute PUT request: " . $e->getMessage(),
                $endpoint,
                null,
                $e
            );
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->circuitBreaker->recordFailure();
            
            Log::error("API Unexpected Error: PUT {$fullUrl}", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'exception_type' => get_class($e),
                'message' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);
            
            throw $e;
        }
    }

    /**
     * Sanitize parameters for logging (remove sensitive data)
     */
    protected function sanitizeParams(array $params): array
    {
        $sensitiveKeys = ['password', 'password_confirmation', 'token', 'api_key', 'secret', 'access_token', 'refresh_token'];
        $sanitized = $params;
        
        foreach ($sensitiveKeys as $key) {
            if (isset($sanitized[$key])) {
                $sanitized[$key] = '***REDACTED***';
            }
        }
        
        return $sanitized;
    }

    /**
     * Generate cache key for endpoint and parameters
     */
    protected function getCacheKey(string $endpoint, array $params = []): string
    {
        $serviceName = $this->getServiceName();
        $hash = md5($endpoint . serialize($params));
        return "api:{$serviceName}:{$hash}";
    }

    /**
     * Invalidate cache for an endpoint
     */
    protected function invalidateCache(string $endpoint): void
    {
        $serviceName = $this->getServiceName();
        $pattern = "api:{$serviceName}:*";
        
        // Note: This is a simplified invalidation. In production, you might want
        // to use cache tags or maintain a list of cache keys
        Cache::flush(); // Simple approach - flush all cache
        // For production, consider using cache tags: Cache::tags([$serviceName])->flush();
    }

    /**
     * Test API connection
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->get('/admin/email-logs', ['per_page' => 1], 0);
            return $response->isSuccess();
        } catch (\Exception $e) {
            Log::error("Connection test failed for {$this->getServiceName()}", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
