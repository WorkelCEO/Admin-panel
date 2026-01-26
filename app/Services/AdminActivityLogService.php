<?php

namespace App\Services;

use App\DTOs\ApiResponse;
use App\Exceptions\ApiException;
use App\Services\Concerns\HandlesApiPagination;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

/**
 * Admin Activity Log Service
 */
class AdminActivityLogService extends BaseApiService
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
        return 'admin_api_activity_logs';
    }

    /**
     * Get all activity logs
     */
    public function getActivityLogs(array $params = []): array
    {
        try {
            $response = $this->get('/activity-logs', $params, 30); // Cache for 30 seconds
            
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
     * Get admin activity logs
     */
    public function getAdminActivityLogs(array $params = []): array
    {
        try {
            $response = $this->get('/activity-logs/admin', $params, 30); // Cache for 30 seconds
            
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
     * Handle unauthorized (401) response by logging out the user
     */
    protected function handleUnauthorized(string $endpoint, array $responseData = []): bool
    {
        // Don't logout on auth endpoints
        if (str_contains($endpoint, '/auth/login') || str_contains($endpoint, '/auth/refresh') || str_contains($endpoint, '/auth/logout')) {
            return false;
        }

        \Illuminate\Support\Facades\Log::warning("API Unauthorized: Logging out user due to 401 error", [
            'service' => $this->getServiceName(),
            'endpoint' => $endpoint,
        ]);

        $adminApiService = app(AdminApiService::class);
        $adminApiService->logoutUser();

        return false;
    }
}
