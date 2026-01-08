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

/**
 * Base API Service with retry logic, circuit breaker, and caching
 */
abstract class BaseApiService
{
    protected string $baseUrl;
    protected ?string $token;
    protected CircuitBreaker $circuitBreaker;
    protected int $maxRetries = 3;
    protected array $retryDelays = [1, 2, 4]; // seconds
    protected int $timeout = 10;
    protected int $retryTimeout = 30;

    public function __construct()
    {
        $this->baseUrl = $this->getBaseUrl();
        $this->token = $this->getToken();
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
     * Get HTTP client with authentication headers
     * Can be overridden by child classes
     */
    protected function client(): PendingRequest
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        $client = Http::withHeaders($headers)->timeout($this->timeout);
        
        // Disable SSL verification if configured (development only)
        $verifySsl = config('services.' . $this->getServiceName() . '.verify_ssl', true);
        if ($verifySsl === false || (is_string($verifySsl) && $verifySsl === 'false')) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    /**
     * Execute GET request with retry logic, circuit breaker, and caching
     */
    protected function get(string $endpoint, array $params = [], ?int $cacheTtl = null): ApiResponse
    {
        // Check circuit breaker
        if (!$this->circuitBreaker->allowsRequest()) {
            Log::warning("Circuit breaker is open for {$this->getServiceName()}", [
                'endpoint' => $endpoint,
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
                Log::debug("Cache hit for {$endpoint}", ['cache_key' => $cacheKey]);
                return $cached;
            }
        }

        $lastException = null;

        // Retry loop
        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                if ($attempt > 0) {
                    $delay = $this->retryDelays[$attempt - 1] ?? end($this->retryDelays);
                    Log::info("Retrying API request", [
                        'endpoint' => $endpoint,
                        'attempt' => $attempt + 1,
                        'delay' => $delay,
                    ]);
                    sleep($delay);
                }

                $response = $this->client()
                    ->timeout($attempt > 0 ? $this->retryTimeout : $this->timeout)
                    ->get($this->baseUrl . $endpoint, $params);

                // Handle different response statuses
                if ($response->successful()) {
                    $this->circuitBreaker->recordSuccess();
                    
                    $data = $response->json();
                    $apiResponse = ApiResponse::success(
                        $data['data'] ?? $data,
                        $data['meta'] ?? []
                    );

                    // Cache successful response
                    if ($cacheTtl !== null) {
                        $cacheKey = $this->getCacheKey($endpoint, $params);
                        Cache::put($cacheKey, $apiResponse, $cacheTtl);
                    }

                    return $apiResponse;
                }

                // Handle specific error statuses
                if ($response->status() === 404) {
                    $this->circuitBreaker->recordSuccess(); // 404 is not a service failure
                    throw new ApiNotFoundException(
                        "Resource not found",
                        $endpoint,
                        ['status' => 404, 'body' => $response->body()]
                    );
                }

                if ($response->status() === 429) {
                    $retryAfter = (int) $response->header('Retry-After', 60);
                    $this->circuitBreaker->recordFailure();
                    throw new ApiRateLimitException(
                        "Rate limit exceeded",
                        $endpoint,
                        $retryAfter,
                        ['status' => 429, 'body' => $response->body()]
                    );
                }

                // Other errors
                $this->circuitBreaker->recordFailure();
                throw new ApiException(
                    "API request failed with status {$response->status()}",
                    $response->status(),
                    null,
                    $endpoint,
                    ['status' => $response->status(), 'body' => $response->body()]
                );

            } catch (ApiException $e) {
                // Re-throw API exceptions immediately
                throw $e;
            } catch (RequestException $e) {
                $lastException = $e;
                
                // Check if it's a timeout
                if (str_contains(strtolower($e->getMessage()), 'timeout') || 
                    str_contains(strtolower($e->getMessage()), 'timed out')) {
                    if ($attempt === $this->maxRetries) {
                        $this->circuitBreaker->recordFailure();
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
                    throw new ApiConnectionException(
                        "Failed to connect to API after {$this->maxRetries} attempts: " . $e->getMessage(),
                        $endpoint,
                        ['attempts' => $attempt + 1],
                        $e
                    );
                }
            } catch (\Exception $e) {
                $lastException = $e;
                if ($attempt === $this->maxRetries) {
                    $this->circuitBreaker->recordFailure();
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
        $this->circuitBreaker->recordFailure();
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
        if (!$this->circuitBreaker->allowsRequest()) {
            throw new ApiConnectionException(
                "Service temporarily unavailable. Circuit breaker is open.",
                $endpoint
            );
        }

        try {
            $response = $this->client()->post($this->baseUrl . $endpoint, $data);

            if ($response->successful()) {
                $this->circuitBreaker->recordSuccess();
                $responseData = $response->json();
                return ApiResponse::success(
                    $responseData['data'] ?? $responseData,
                    $responseData['meta'] ?? []
                );
            }

            $this->circuitBreaker->recordFailure();
            throw new ApiException(
                "POST request failed with status {$response->status()}",
                $response->status(),
                null,
                $endpoint,
                ['status' => $response->status(), 'body' => $response->body()]
            );
        } catch (RequestException $e) {
            $this->circuitBreaker->recordFailure();
            throw new ApiConnectionException(
                "Failed to execute POST request: " . $e->getMessage(),
                $endpoint,
                null,
                $e
            );
        }
    }

    /**
     * Execute DELETE request
     */
    protected function delete(string $endpoint): bool
    {
        if (!$this->circuitBreaker->allowsRequest()) {
            throw new ApiConnectionException(
                "Service temporarily unavailable. Circuit breaker is open.",
                $endpoint
            );
        }

        try {
            $response = $this->client()->delete($this->baseUrl . $endpoint);

            if ($response->successful()) {
                $this->circuitBreaker->recordSuccess();
                
                // Invalidate cache for this endpoint
                $this->invalidateCache($endpoint);
                
                return true;
            }

            $this->circuitBreaker->recordFailure();
            return false;
        } catch (RequestException $e) {
            $this->circuitBreaker->recordFailure();
            throw new ApiConnectionException(
                "Failed to execute DELETE request: " . $e->getMessage(),
                $endpoint,
                null,
                $e
            );
        }
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
