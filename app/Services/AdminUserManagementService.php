<?php

namespace App\Services;

use App\DTOs\ApiResponse;
use App\DTOs\UserResponse;
use App\Exceptions\ApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

/**
 * Admin User Management Service
 */
class AdminUserManagementService extends BaseApiService
{
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
        return 'admin_api_users';
    }

    /**
     * Get all users
     */
    public function getUsers(array $params = []): array
    {
        try {
            $response = $this->get('/users', $params, 0); // Don't cache for sync operations
            
            // Log raw response for debugging
            \Illuminate\Support\Facades\Log::debug('Admin API getUsers response', [
                'params' => $params,
                'response_data' => $response->data,
                'response_type' => gettype($response->data),
            ]);
            
            // Handle different response structures
            // Admin API might return: { data: [...], meta: {...} } or { data: { data: [...], meta: {...} } }
            $responseData = $response->data;
            
            // Check if data is nested
            if (isset($responseData['data']) && is_array($responseData['data'])) {
                // Structure: { data: { data: [...], meta: {...} } }
                $data = $responseData['data'];
                $meta = $responseData['meta'] ?? [];
            } elseif (is_array($responseData) && isset($responseData[0])) {
                // Structure: { data: [...] } - direct array
                $data = $responseData;
                $meta = [];
            } else {
                // Structure: { data: [...], meta: {...} } - flat structure
                $data = $responseData['data'] ?? [];
                $meta = $responseData['meta'] ?? [];
            }
            
            // Extract pagination from meta or response
            $currentPage = $meta['current_page'] ?? $responseData['current_page'] ?? 1;
            $perPage = $meta['per_page'] ?? $responseData['per_page'] ?? 15;
            $total = $meta['total'] ?? $responseData['total'] ?? (is_array($data) ? count($data) : 0);
            $lastPage = $meta['last_page'] ?? $responseData['last_page'] ?? 1;
            
            return [
                'data' => is_array($data) ? $data : [],
                'meta' => [
                    'current_page' => (int) $currentPage,
                    'per_page' => (int) $perPage,
                    'total' => (int) $total,
                    'last_page' => (int) $lastPage,
                ],
            ];
        } catch (ApiException $e) {
            \Illuminate\Support\Facades\Log::error('Admin API getUsers error', [
                'message' => $e->getMessage(),
                'endpoint' => $e->getEndpoint(),
                'context' => $e->getContext(),
            ]);
            
            return [
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ];
        }
    }

    /**
     * Get user details
     */
    public function getUser(string $userId): ?UserResponse
    {
        try {
            $response = $this->get("/users/{$userId}", [], 300); // Cache for 5 minutes
            
            if (empty($response->data)) {
                return null;
            }

            return UserResponse::fromApiResponse([
                'data' => $response->data,
                'success' => true,
            ]);
        } catch (ApiException $e) {
            return null;
        }
    }

    /**
     * Update user
     */
    public function updateUser(string $userId, array $data): ?UserResponse
    {
        try {
            $response = $this->put("/users/{$userId}", $data);
            
            return UserResponse::fromApiResponse([
                'data' => $response->data,
                'success' => $response->success,
                'message' => $response->message,
            ]);
        } catch (ApiException $e) {
            return null;
        }
    }

    /**
     * Change user role
     */
    public function changeUserRole(string $userId, string $role): ApiResponse
    {
        try {
            return $this->post("/users/{$userId}/change-role", ['role' => $role]);
        } catch (ApiException $e) {
            return ApiResponse::failure($e->getMessage());
        }
    }

    /**
     * Suspend user
     */
    public function suspendUser(string $userId, string $reason, ?string $suspendedUntil = null): ApiResponse
    {
        try {
            $data = ['reason' => $reason];
            if ($suspendedUntil) {
                $data['suspended_until'] = $suspendedUntil;
            }
            
            return $this->post("/users/{$userId}/suspend", $data);
        } catch (ApiException $e) {
            return ApiResponse::failure($e->getMessage());
        }
    }

    /**
     * Unsuspend user
     */
    public function unsuspendUser(string $userId): ApiResponse
    {
        try {
            return $this->post("/users/{$userId}/unsuspend", []);
        } catch (ApiException $e) {
            return ApiResponse::failure($e->getMessage());
        }
    }

    /**
     * Delete user
     */
    public function deleteUser(string $userId): bool
    {
        try {
            return $this->delete("/users/{$userId}");
        } catch (ApiException $e) {
            return false;
        }
    }

    /**
     * Get user activity
     */
    public function getUserActivity(string $userId, array $params = []): array
    {
        try {
            $response = $this->get("/users/{$userId}/activity", $params, 60);
            
            return [
                'data' => $response->data['data'] ?? [],
                'meta' => $response->data['meta'] ?? [],
            ];
        } catch (ApiException $e) {
            return [
                'data' => [],
                'meta' => [],
            ];
        }
    }

    /**
     * Get user login history
     */
    public function getUserLoginHistory(string $userId, array $params = []): array
    {
        try {
            $response = $this->get("/users/{$userId}/login-history", $params, 60);
            
            return [
                'data' => $response->data['data'] ?? [],
                'meta' => $response->data['meta'] ?? [],
            ];
        } catch (ApiException $e) {
            return [
                'data' => [],
                'meta' => [],
            ];
        }
    }

    /**
     * Override client to include admin token
     */
    protected function client(): PendingRequest
    {
        $client = parent::client();
        
        $token = $this->getToken();
        if ($token) {
            $client->withToken($token);
        }

        return $client;
    }

    /**
     * Execute PUT request
     */
    protected function put(string $endpoint, array $data = []): ApiResponse
    {
        if (!$this->circuitBreaker->allowsRequest()) {
            throw new \App\Exceptions\ApiConnectionException(
                "Service temporarily unavailable. Circuit breaker is open.",
                $endpoint
            );
        }

        try {
            $response = $this->client()->put($this->baseUrl . $endpoint, $data);

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
                "PUT request failed with status {$response->status()}",
                $response->status(),
                null,
                $endpoint,
                ['status' => $response->status(), 'body' => $response->body()]
            );
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $this->circuitBreaker->recordFailure();
            throw new \App\Exceptions\ApiConnectionException(
                "Failed to execute PUT request: " . $e->getMessage(),
                $endpoint,
                null,
                $e
            );
        }
    }
}
