<?php

namespace App\Services;

use App\DTOs\ApiResponse;
use App\Exceptions\ApiException;
use Illuminate\Http\Client\PendingRequest;

/**
 * Admin Settings Service
 */
class AdminSettingsService extends BaseApiService
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
        return 'admin_api_settings';
    }

    /**
     * Get settings
     */
    public function getSettings(): ApiResponse
    {
        try {
            return $this->get('/settings', [], 300); // Cache for 5 minutes
        } catch (ApiException $e) {
            return ApiResponse::failure($e->getMessage());
        }
    }

    /**
     * Update settings
     */
    public function updateSettings(array $settings): ApiResponse
    {
        try {
            return $this->post('/settings/update', $settings);
        } catch (ApiException $e) {
            return ApiResponse::failure($e->getMessage());
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
