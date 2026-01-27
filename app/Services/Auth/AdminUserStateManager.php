<?php

namespace App\Services\Auth;

use App\Services\AdminApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * Admin User State Manager
 * 
 * Manages user data synchronization between API and local storage
 */
class AdminUserStateManager
{
    private const USER_SESSION_KEY = 'admin_user';
    private const USER_CACHE_KEY = 'admin_user';
    private const USER_CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private AdminApiService $adminApiService
    ) {
    }

    /**
     * Get user data from session or cache
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

        return null;
    }

    /**
     * Store user data in session and cache
     */
    public function storeUser(array $userData): void
    {
        Session::put(self::USER_SESSION_KEY, $userData);
        Cache::put(self::USER_CACHE_KEY, $userData, self::USER_CACHE_TTL);
        
        Log::debug('User state stored', [
            'user_id' => $userData['id'] ?? null,
            'email' => $userData['email'] ?? null,
        ]);
    }

    /**
     * Synchronize user state from API
     */
    public function syncFromApi(): bool
    {
        try {
            $response = $this->adminApiService->getCurrentAdmin();
            
            if ($response->isSuccess() && !empty($response->data)) {
                $userData = $response->data['user'] ?? $response->data;
                
                if (is_array($userData) && !empty($userData)) {
                    $this->storeUser($userData);
                    Log::info('User state synchronized from API', [
                        'user_id' => $userData['id'] ?? null,
                    ]);
                    return true;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to sync user state from API', [
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Clear user state
     */
    public function clearUser(): void
    {
        Session::forget(self::USER_SESSION_KEY);
        Cache::forget(self::USER_CACHE_KEY);
        
        Log::debug('User state cleared');
    }

    /**
     * Check if user data exists
     */
    public function hasUser(): bool
    {
        return $this->getUser() !== null;
    }
}
