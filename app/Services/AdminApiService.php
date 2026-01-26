<?php

namespace App\Services;

use App\DTOs\AdminAuthResponse;
use App\DTOs\ApiResponse;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * Admin API Service for authentication
 */
class AdminApiService extends BaseApiService
{
    private TokenManager $tokenManager;

    public function __construct()
    {
        parent::__construct();
        $this->tokenManager = new TokenManager();
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
        return 'admin_api';
    }

    /**
     * Store admin token
     */
    public function storeToken(string $token, ?string $expiresAt = null): void
    {
        $this->tokenManager->store($token, $expiresAt);
    }

    /**
     * Get stored token
     */
    public function getStoredToken(): ?string
    {
        return $this->tokenManager->get();
    }

    /**
     * Check if token is expired
     */
    public function isTokenExpired(): bool
    {
        return $this->tokenManager->isExpired();
    }

    /**
     * Clear stored token
     */
    public function clearToken(): void
    {
        $this->tokenManager->clear();
    }

    /**
     * Admin login
     * 
     * According to API documentation:
     * POST /api/admin/auth/login
     * Body: { email, password, device_name (optional) }
     * Response: { success: true, message: "...", data: { user: {...}, token: "...", token_type: "Bearer", expires_at: "..." } }
     */
    public function login(string $email, string $password, ?string $deviceName = null): AdminAuthResponse
    {
        $data = [
            'email' => $email,
            'password' => $password,
        ];

        if ($deviceName) {
            $data['device_name'] = $deviceName;
        }

        try {
            // Login endpoint doesn't require token
            $response = $this->httpClient->post('/auth/login', $data, null);

            // Parse response to AdminAuthResponse
            $authResponse = AdminAuthResponse::fromApiResponse([
                'data' => $response->data,
                'success' => $response->success,
                'message' => $response->message,
            ]);

            // Store token if login successful
            if ($authResponse->isSuccess() && $authResponse->getToken()) {
                $this->tokenManager->store(
                    $authResponse->getToken(),
                    $authResponse->getExpiresAt()
                );
                Log::info('Admin token stored successfully');
            } else {
                Log::warning('Admin login response missing token', [
                    'success' => $authResponse->isSuccess(),
                    'has_token' => !empty($authResponse->getToken()),
                    'message' => $authResponse->message,
                ]);
            }

            return $authResponse;
        } catch (ApiException $e) {
            Log::error('Admin login API exception', [
                'message' => $e->getMessage(),
                'endpoint' => $e->getEndpoint(),
                'context' => $e->getContext(),
            ]);

            // ErrorHandler already provides user-friendly messages
            return AdminAuthResponse::failure($e->getMessage());
        }
    }

    /**
     * Admin logout
     */
    public function logout(bool $revokeAll = false): ApiResponse
    {
        try {
            $data = $revokeAll ? ['revoke_all' => true] : [];
            $response = $this->post('/auth/logout', $data);
            
            // Clear stored token
            $this->tokenManager->clear();
            
            return $response;
        } catch (ApiException $e) {
            // Clear token even if logout fails
            $this->tokenManager->clear();
            return ApiResponse::failure($e->getMessage());
        }
    }

    /**
     * Refresh admin token
     */
    public function refreshToken(): AdminAuthResponse
    {
        try {
            $token = $this->tokenManager->get();
            if (!$token) {
                Log::warning("Token refresh attempted but no token available");
                $this->tokenManager->clear();
                return AdminAuthResponse::failure("No token available to refresh");
            }

            // Use current token for refresh
            $response = $this->httpClient->post('/auth/refresh', [], $token);

            // Parse response to AdminAuthResponse
            $authResponse = AdminAuthResponse::fromApiResponse([
                'data' => $response->data,
                'success' => $response->success,
                'message' => $response->message,
            ]);

            // Update stored token
            if ($authResponse->isSuccess() && $authResponse->getToken()) {
                $this->tokenManager->store(
                    $authResponse->getToken(),
                    $authResponse->getExpiresAt()
                );
                Log::info("Token refreshed successfully");
            }

            return $authResponse;
        } catch (ApiException $e) {
            Log::warning("Token refresh failed", [
                'error' => $e->getMessage(),
                'status_code' => $e->getCode(),
            ]);
            
            $this->tokenManager->clear();
            return AdminAuthResponse::failure($e->getMessage());
        }
    }

    /**
     * Get current admin info
     */
    public function getCurrentAdmin(): ApiResponse
    {
        try {
            return $this->get('/auth/me', [], 60); // Cache for 60 seconds
        } catch (ApiException $e) {
            return ApiResponse::failure($e->getMessage());
        }
    }

    /**
     * Handle unauthorized (401) response by attempting token refresh
     */
    protected function handleUnauthorized(string $endpoint, array $responseData = []): bool
    {
        // Don't attempt refresh on auth endpoints to avoid infinite loops
        if (str_contains($endpoint, '/auth/login') || 
            str_contains($endpoint, '/auth/refresh') || 
            str_contains($endpoint, '/auth/logout')) {
            return false;
        }

        // Attempt to refresh token
        try {
            $refreshResponse = $this->refreshToken();
            if ($refreshResponse->isSuccess() && $refreshResponse->getToken()) {
                Log::info("Token refreshed successfully, retrying request", [
                    'endpoint' => $endpoint,
                ]);
                return true;
            }
        } catch (\Exception $e) {
            Log::warning("Token refresh failed during unauthorized handling", [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
        }

        // If refresh fails, logout user
        $this->logoutUser();
        return false;
    }

    /**
     * Logout the user and clear all authentication data
     * Made public so other services can call it
     */
    public function logoutUser(): void
    {
        try {
            // Clear token
            $this->tokenManager->clear();
            
            // Clear admin user from session
            Session::forget('admin_user');
            
            // Logout Filament user if authenticated
            if (\Illuminate\Support\Facades\Auth::check()) {
                \Illuminate\Support\Facades\Auth::logout();
                Session::invalidate();
                Session::regenerateToken();
            }
            
            Log::info("User logged out due to authentication failure");
        } catch (\Exception $e) {
            Log::error("Error during user logout", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
