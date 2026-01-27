<?php

namespace App\Services;

use App\DTOs\ApiResponse;
use App\DTOs\SystemHealthResponse;
use App\DTOs\SystemInfoResponse;
use App\DTOs\SystemStatsResponse;
use App\Exceptions\ApiException;
use Illuminate\Http\Client\PendingRequest;

/**
 * Admin System Service
 */
class AdminSystemService extends BaseApiService
{
    private TokenManager $tokenManager;

    public function __construct()
    {
        parent::__construct();
        $this->tokenManager = app(TokenManager::class);
    }

    protected function getBaseUrl(): string
    {
        return config('services.admin_api.base_url', env('ADMIN_API_BASE_URL', 'https://your-domain.com/api/admin'));
    }

    protected function getToken(): ?string
    {
        return $this->tokenManager->get();
    }

    protected function getServiceName(): string
    {
        return 'admin_api_system';
    }

    /**
     * Get system information
     */
    public function getSystemInfo(): ?SystemInfoResponse
    {
        try {
            $response = $this->get('/system/info', [], 300); // Cache for 5 minutes
            
            return SystemInfoResponse::fromApiResponse([
                'data' => $response->data,
                'success' => $response->success,
            ]);
        } catch (ApiException $e) {
            return null;
        }
    }

    /**
     * Get system health
     */
    public function getSystemHealth(): ?SystemHealthResponse
    {
        try {
            $response = $this->get('/system/health', [], 60); // Cache for 60 seconds
            
            return SystemHealthResponse::fromApiResponse([
                'data' => $response->data,
                'success' => $response->success,
            ]);
        } catch (ApiException $e) {
            return null;
        }
    }

    /**
     * Get system statistics
     */
    public function getSystemStats(): ?SystemStatsResponse
    {
        try {
            $response = $this->get('/system/stats', [], 120); // Cache for 2 minutes
            
            return SystemStatsResponse::fromApiResponse([
                'data' => $response->data,
                'success' => $response->success,
            ]);
        } catch (ApiException $e) {
            return null;
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
