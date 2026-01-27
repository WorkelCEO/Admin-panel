<?php

namespace App\Services;

use App\DTOs\ApiResponse;
use App\Exceptions\ApiException;
use App\Services\Http\ApiHttpClient;
use App\Services\Http\ErrorHandler;
use App\Services\Http\ResponseParser;
use App\Services\Http\RetryHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Base API Service with retry logic, circuit breaker, and caching
 */
abstract class BaseApiService
{
    protected ApiHttpClient $httpClient;
    protected CircuitBreaker $circuitBreaker;
    protected int $maxRetries = 3;
    protected array $retryDelays = [1, 2, 4]; // seconds
    protected int $timeout = 10;
    protected int $retryTimeout = 30;

    public function __construct()
    {
        $baseUrl = $this->getBaseUrl();
        $serviceName = $this->getServiceName();
        
        $this->circuitBreaker = new CircuitBreaker($serviceName);
        
        $responseParser = new ResponseParser();
        $errorHandler = new ErrorHandler($responseParser);
        $retryHandler = new RetryHandler(
            $this->maxRetries,
            $this->retryDelays,
            $this->timeout,
            $this->retryTimeout
        );
        
        $verifySsl = $this->shouldVerifySsl();
        
        $this->httpClient = new ApiHttpClient(
            $baseUrl,
            $serviceName,
            $this->circuitBreaker,
            $responseParser,
            $errorHandler,
            $retryHandler,
            $verifySsl
        );
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
     * Check if SSL verification should be enabled
     */
    protected function shouldVerifySsl(): bool
    {
        $verifySsl = config('services.' . $this->getServiceName() . '.verify_ssl', true);
        $envVerifySsl = env(strtoupper($this->getServiceName()) . '_VERIFY_SSL', null);
        
        // Handle boolean and string values for SSL verification
        if ($verifySsl === false || $verifySsl === 'false' || 
            ($envVerifySsl !== null && ($envVerifySsl === false || $envVerifySsl === 'false'))) {
            return false;
        }
        
        return true;
    }

    /**
     * Execute GET request with retry logic, circuit breaker, and caching
     */
    protected function get(string $endpoint, array $params = [], ?int $cacheTtl = null): ApiResponse
    {
        // Check cache for GET requests
        if ($cacheTtl !== null) {
            $cacheKey = $this->getCacheKey($endpoint, $params);
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                Log::debug("API Cache Hit: GET {$endpoint}", [
                    'service' => $this->getServiceName(),
                    'endpoint' => $endpoint,
                    'cache_key' => $cacheKey,
                ]);
                return $cached;
            }
        }

        // Get token for authenticated requests
        $token = $this->getToken();
        
        // Check if token is missing for authenticated endpoints
        if (empty($token) && !$this->isPublicEndpoint($endpoint)) {
            Log::warning("Token missing for authenticated endpoint", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
            ]);
            
            if (!$this->handleUnauthorized($endpoint, [])) {
                throw new ApiException(
                    "Authentication token is missing. Please log in again.",
                    401,
                    null,
                    $endpoint,
                    ['status' => 401, 'requires_auth' => true, 'token_missing' => true]
                );
            }
            $token = $this->getToken();
        }

        // Log token status for debugging
        if (!$this->isPublicEndpoint($endpoint)) {
            Log::debug("Making authenticated request", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'has_token' => !empty($token),
                'token_preview' => $token ? substr($token, 0, 20) . '...' : null,
            ]);
        }

        // Make request
        $response = $this->httpClient->get($endpoint, $params, $token);

        // Cache successful response
        if ($cacheTtl !== null && $response->isSuccess()) {
            $cacheKey = $this->getCacheKey($endpoint, $params);
            Cache::put($cacheKey, $response, $cacheTtl);
            Log::debug("API Response Cached: GET {$endpoint}", [
                'service' => $this->getServiceName(),
                'cache_key' => $cacheKey,
                'cache_ttl_seconds' => $cacheTtl,
            ]);
        }

        return $response;
    }

    /**
     * Execute POST request
     */
    protected function post(string $endpoint, array $data = [], ?string $token = null): ApiResponse
    {
        $token = $token ?? $this->getToken();
        
        // Handle 401 by attempting token refresh
        try {
            return $this->httpClient->post($endpoint, $data, $token);
        } catch (ApiException $e) {
            if ($e->getCode() === 401 && $this->handleUnauthorized($endpoint, $e->getContext()['response_data'] ?? [])) {
                // Retry with refreshed token
                return $this->httpClient->post($endpoint, $data, $this->getToken());
            }
            throw $e;
        }
    }

    /**
     * Execute DELETE request
     */
    protected function delete(string $endpoint, ?string $token = null): bool
    {
        $token = $token ?? $this->getToken();
        
        $result = $this->httpClient->delete($endpoint, $token);
        
        // Invalidate cache for this endpoint
        if ($result) {
            $this->invalidateCache($endpoint);
        }
        
        return $result;
    }

    /**
     * Execute PUT request
     */
    protected function put(string $endpoint, array $data = [], ?string $token = null): ApiResponse
    {
        $token = $token ?? $this->getToken();
        
        // Handle 401 by attempting token refresh
        try {
            return $this->httpClient->put($endpoint, $data, $token);
        } catch (ApiException $e) {
            if ($e->getCode() === 401 && $this->handleUnauthorized($endpoint, $e->getContext()['response_data'] ?? [])) {
                // Retry with refreshed token
                return $this->httpClient->put($endpoint, $data, $this->getToken());
            }
            throw $e;
        }
    }

    /**
     * Check if endpoint is public (doesn't require authentication)
     */
    protected function isPublicEndpoint(string $endpoint): bool
    {
        return str_contains($endpoint, '/auth/login') || 
               str_contains($endpoint, '/auth/refresh');
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
