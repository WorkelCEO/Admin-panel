<?php

namespace App\Services;

use App\DTOs\ApiResponse;
use App\DTOs\UserResponse;
use App\Exceptions\ApiException;
use App\Services\Concerns\HandlesApiPagination;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

/**
 * Admin User Management Service
 */
class AdminUserManagementService extends BaseApiService
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
        return 'admin_api_users';
    }

    /**
     * Get all users
     */
    public function getUsers(array $params = []): array
    {
        try {
            // Disable caching for paginated requests to ensure fresh data
            // Pass null instead of 0 to completely disable caching
            $response = $this->get('/users', $params, null);
            
            // Use standardized pagination extraction
            return $this->extractPaginatedData($response);
        } catch (ApiException $e) {
            \Illuminate\Support\Facades\Log::error('Admin API getUsers error', [
                'message' => $e->getMessage(),
                'endpoint' => $e->getEndpoint(),
                'context' => $e->getContext(),
            ]);
            
            // Return empty paginated response
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
     * PUT method is inherited from BaseApiService with full logging
     * No override needed - uses parent::put() which includes comprehensive logging
     */
}
