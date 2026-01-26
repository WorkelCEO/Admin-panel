<?php

namespace App\Services;

use App\DTOs\ApiResponse;
use App\Exceptions\ApiException;
use App\Services\Concerns\HandlesApiPagination;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * Admin Login History Service
 */
class AdminLoginHistoryService extends BaseApiService
{
    use HandlesApiPagination;
    protected function getBaseUrl(): string
    {
        return config('services.admin_api.base_url', env('ADMIN_API_BASE_URL', 'https://your-domain.com/api/admin'));
    }

    protected function getToken(): ?string
    {
        return Session::get('admin_api_token') ?? Cache::get('admin_api_token');
    }

    protected function getServiceName(): string
    {
        return 'admin_api_login_history';
    }

    /**
     * Get all login history
     */
    public function getLoginHistory(array $params = []): array
    {
        try {
            $response = $this->get('/login-history', $params, 30); // Cache for 30 seconds
            
            // Use standardized pagination extraction
            return $this->extractPaginatedData($response);
        } catch (ApiException $e) {
            return [
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => null,
                    'to' => null,
                ],
            ];
        }
    }

    /**
     * Get login history for a specific user
     */
    public function getUserLoginHistory(string $userId, array $params = []): array
    {
        try {
            $response = $this->get("/login-history/user/{$userId}", $params, 30);
            
            // Use standardized pagination extraction
            return $this->extractPaginatedData($response);
        } catch (ApiException $e) {
            return [
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => null,
                    'to' => null,
                ],
            ];
        }
    }

    /**
     * Get login history statistics
     */
    public function getLoginStatistics(): array
    {
        try {
            $response = $this->get('/login-history/statistics', [], 60); // Cache for 60 seconds
            
            return $response->data ?? [];
        } catch (ApiException $e) {
            return [];
        }
    }

    /**
     * Cleanup old login history records
     */
    public function cleanupLoginHistory(?string $olderThan = null): ApiResponse
    {
        $startTime = microtime(true);
        $endpoint = '/login-history/cleanup';
        $fullUrl = $this->baseUrl . $endpoint;
        $params = [];
        
        if ($olderThan) {
            $params['older_than'] = $olderThan;
            $fullUrl .= '?' . http_build_query($params);
        }
        
        // Log API request
        Log::info("API Request: DELETE {$fullUrl}", [
            'service' => $this->getServiceName(),
            'endpoint' => $endpoint,
            'params' => $params,
        ]);
        
        try {
            // Use base class delete method instead, but we need ApiResponse, so implement manually
            // Check circuit breaker
            if (!$this->circuitBreaker->allowsRequest()) {
                Log::warning("Circuit breaker is open for DELETE {$fullUrl}", [
                    'service' => $this->getServiceName(),
                    'endpoint' => $endpoint,
                ]);
                
                throw new \App\Exceptions\ApiConnectionException(
                    "Service temporarily unavailable. Circuit breaker is open.",
                    $endpoint
                );
            }
            
            $response = $this->client()
                ->timeout($this->timeout)
                ->delete($fullUrl);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $statusCode = $response->status();
            $responseSize = strlen($response->body());
            
            if ($response->successful()) {
                $this->circuitBreaker->recordSuccess();
                $data = $response->json();
                
                // Log successful API response
                Log::info("API Response: DELETE {$fullUrl} - Success", [
                    'service' => $this->getServiceName(),
                    'endpoint' => $endpoint,
                    'status_code' => $statusCode,
                    'duration_ms' => $duration,
                    'response_size_bytes' => $responseSize,
                    'has_data' => !empty($data),
                ]);
                
                return ApiResponse::success(
                    $data['data'] ?? ['message' => 'Cleanup completed successfully'],
                    $data['meta'] ?? []
                );
            }
            
            $this->circuitBreaker->recordFailure();
            $data = $response->json();
            
            Log::error("API Response: DELETE {$fullUrl} - Error", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'duration_ms' => $duration,
                'response_preview' => substr($response->body(), 0, 500),
            ]);
            
            return ApiResponse::failure(
                $data['message'] ?? "DELETE request failed with status {$statusCode}",
                $data['data'] ?? null,
                $data['meta'] ?? []
            );
        } catch (\App\Exceptions\ApiException $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error("API Exception: DELETE {$fullUrl}", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'exception_type' => get_class($e),
                'message' => $e->getMessage(),
                'duration_ms' => $duration,
                'context' => $e->getContext(),
            ]);
            
            throw $e;
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error("API Unexpected Error: DELETE {$fullUrl}", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'exception_type' => get_class($e),
                'message' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);
            
            throw new \App\Exceptions\ApiException(
                "Failed to cleanup login history: " . $e->getMessage(),
                500,
                null,
                '/login-history/cleanup',
                ['exception' => get_class($e)]
            );
        }
    }

    /**
     * Override client to include admin token
     * BaseApiService already adds token via Authorization header in parent::client()
     * No need to override - parent handles it correctly
     */
    protected function client(): PendingRequest
    {
        $client = parent::client();
        
        // Log token status for debugging
        $token = $this->getToken();
        if ($token) {
            Log::debug("AdminLoginHistoryService: Token available for request", [
                'service' => $this->getServiceName(),
                'has_token' => true,
                'token_preview' => substr($token, 0, 20) . '...',
            ]);
        } else {
            Log::warning("AdminLoginHistoryService: No token available for request", [
                'service' => $this->getServiceName(),
                'session_has_token' => Session::has('admin_api_token'),
                'cache_has_token' => Cache::has('admin_api_token'),
            ]);
        }

        return $client;
    }

    /**
     * Handle unauthorized (401) response by logging out the user
     */
    protected function handleUnauthorized(string $endpoint, array $responseData = []): bool
    {
        // Don't logout on auth endpoints
        if (str_contains($endpoint, '/auth/login') || str_contains($endpoint, '/auth/refresh') || str_contains($endpoint, '/auth/logout')) {
            return false;
        }

        Log::warning("API Unauthorized: Logging out user due to 401 error", [
            'service' => $this->getServiceName(),
            'endpoint' => $endpoint,
        ]);

        $adminApiService = app(AdminApiService::class);
        $adminApiService->logoutUser();

        return false;
    }
}