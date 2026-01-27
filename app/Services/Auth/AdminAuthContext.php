<?php

namespace App\Services\Auth;

use App\DTOs\AdminAuthResponse;
use App\Services\AdminApiService;
use App\Services\TokenManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * Admin Authentication Context - Centralized authentication state management
 * 
 * Singleton service that manages:
 * - Authentication state
 * - Token management
 * - User state synchronization
 * - Proactive token refresh
 */
class AdminAuthContext
{
    private const USER_SESSION_KEY = 'admin_user';
    private const USER_CACHE_KEY = 'admin_user';
    private const USER_CACHE_TTL = 3600; // 1 hour

    private ?AdminApiService $adminApiService = null;

    public function __construct(
        private TokenManager $tokenManager
    ) {
    }

    /**
     * Get AdminApiService instance (lazy loading to avoid circular dependency)
     */
    private function getAdminApiService(): AdminApiService
    {
        if ($this->adminApiService === null) {
            $this->adminApiService = app(AdminApiService::class);
        }
        return $this->adminApiService;
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        $token = $this->tokenManager->get();
        return !empty($token);
    }

    /**
     * Get current authentication token
     */
    public function getToken(): ?string
    {
        return $this->tokenManager->get();
    }

    /**
     * Get current user data
     */
    public function getUser(): ?array
    {
        // Try session first
        $user = Session::get(self::USER_SESSION_KEY);
        if ($user) {
            return $user;
        }

        // Try cache
        $user = Cache::get(self::USER_CACHE_KEY);
        if ($user) {
            // Restore to session
            Session::put(self::USER_SESSION_KEY, $user);
            return $user;
        }

        // If no user data but we have a token, try to sync
        if ($this->isAuthenticated()) {
            $this->syncUserState();
            return Session::get(self::USER_SESSION_KEY) ?? Cache::get(self::USER_CACHE_KEY);
        }

        return null;
    }

    /**
     * Ensure user is authenticated, refresh token if needed
     * 
     * @throws \App\Exceptions\ApiException If authentication fails
     */
    public function ensureAuthenticated(): void
    {
        if (!$this->isAuthenticated()) {
            throw new \App\Exceptions\ApiException(
                'User is not authenticated. Please log in.',
                401,
                null,
                '/auth/me',
                ['requires_auth' => true]
            );
        }

        // Proactively refresh token if needed
        $this->refreshTokenIfNeeded();
    }

    /**
     * Synchronize user state from API
     */
    public function syncUserState(): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        try {
            $response = $this->getAdminApiService()->getCurrentAdmin();
            
            if ($response->isSuccess() && !empty($response->data)) {
                $userData = $response->data['user'] ?? $response->data;
                
                if (is_array($userData) && !empty($userData)) {
                    $this->storeUser($userData);
                    Log::debug('User state synchronized from API');
                    return true;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to sync user state', [
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Refresh token if it's expiring soon
     * 
     * @return bool True if token was refreshed, false otherwise
     */
    public function refreshTokenIfNeeded(): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        // Check if token is expired or expiring soon
        if (!$this->tokenManager->isExpired()) {
            return false; // Token is still valid
        }

        try {
            Log::info('Token expiring soon, refreshing proactively');
            
            $refreshResponse = $this->getAdminApiService()->refreshToken();
            
            if ($refreshResponse->isSuccess() && $refreshResponse->getToken()) {
                // Sync user state after token refresh
                $this->syncUserState();
                Log::info('Token refreshed successfully');
                return true;
            }
        } catch (\Exception $e) {
            Log::warning('Proactive token refresh failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Store user data in session and cache
     */
    public function storeUser(array $userData): void
    {
        Session::put(self::USER_SESSION_KEY, $userData);
        Cache::put(self::USER_CACHE_KEY, $userData, self::USER_CACHE_TTL);
    }

    /**
     * Clear all authentication data
     */
    public function clearAuth(): void
    {
        $this->tokenManager->clear();
        Session::forget(self::USER_SESSION_KEY);
        Cache::forget(self::USER_CACHE_KEY);
        
        Log::info('Authentication context cleared');
    }

    /**
     * Set authentication state after successful login
     */
    public function setAuthenticated(AdminAuthResponse $authResponse): void
    {
        if (!$authResponse->isSuccess() || !$authResponse->getToken()) {
            return;
        }

        // Token is already stored by AdminApiService
        // Just sync user state
        $userData = $authResponse->getUser();
        if ($userData) {
            $this->storeUser($userData);
        } else {
            // If user data not in response, fetch it
            $this->syncUserState();
        }
    }

    /**
     * Check if token exists and is valid
     */
    public function hasValidToken(): bool
    {
        return $this->isAuthenticated() && !$this->tokenManager->isExpired();
    }
}
