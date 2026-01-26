<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

/**
 * Token Manager - Centralized token storage and retrieval
 */
class TokenManager
{
    private const SESSION_KEY = 'admin_api_token';
    private const SESSION_EXPIRES_KEY = 'admin_api_token_expires_at';
    private const CACHE_KEY = 'admin_api_token';
    private const CACHE_EXPIRES_KEY = 'admin_api_token_expires_at';
    private const EXPIRATION_BUFFER_MINUTES = 5;

    /**
     * Store token in session and cache
     */
    public function store(string $token, ?string $expiresAt = null): void
    {
        Session::put(self::SESSION_KEY, $token);
        Cache::put(self::CACHE_KEY, $token, $this->calculateCacheTtl($expiresAt));

        if ($expiresAt) {
            Session::put(self::SESSION_EXPIRES_KEY, $expiresAt);
            Cache::put(
                self::CACHE_EXPIRES_KEY,
                $expiresAt,
                $this->calculateCacheTtl($expiresAt)
            );
        }
    }

    /**
     * Get token from session or cache
     */
    public function get(): ?string
    {
        return Session::get(self::SESSION_KEY) ?? Cache::get(self::CACHE_KEY);
    }

    /**
     * Check if token exists
     */
    public function has(): bool
    {
        return $this->get() !== null;
    }

    /**
     * Check if token is expired or will expire soon
     */
    public function isExpired(): bool
    {
        $expiresAt = $this->getExpiresAt();
        
        if (!$expiresAt) {
            return false; // No expiration set
        }

        $expirationTime = now()->parse($expiresAt);
        $bufferTime = now()->addMinutes(self::EXPIRATION_BUFFER_MINUTES);

        return $bufferTime->greaterThan($expirationTime);
    }

    /**
     * Get token expiration time
     */
    public function getExpiresAt(): ?string
    {
        return Session::get(self::SESSION_EXPIRES_KEY) 
            ?? Cache::get(self::CACHE_EXPIRES_KEY);
    }

    /**
     * Clear token from session and cache
     */
    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget(self::SESSION_EXPIRES_KEY);
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_EXPIRES_KEY);
    }

    /**
     * Calculate cache TTL from expiration time
     */
    private function calculateCacheTtl(?string $expiresAt): int
    {
        if (!$expiresAt) {
            return 3600; // Default 1 hour
        }

        $expirationTime = now()->parse($expiresAt);
        $secondsUntilExpiration = max(0, now()->diffInSeconds($expirationTime, false));

        return $secondsUntilExpiration > 0 ? $secondsUntilExpiration : 3600;
    }
}
