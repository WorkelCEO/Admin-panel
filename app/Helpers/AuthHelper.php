<?php

if (!function_exists('admin_auth')) {
    /**
     * Get AdminAuthContext instance
     * 
     * @return \App\Services\Auth\AdminAuthContext
     */
    function admin_auth(): \App\Services\Auth\AdminAuthContext
    {
        return app(\App\Services\Auth\AdminAuthContext::class);
    }
}

if (!function_exists('admin_user')) {
    /**
     * Get current admin user data
     * 
     * @return array|null
     */
    function admin_user(): ?array
    {
        return admin_auth()->getUser();
    }
}

if (!function_exists('admin_token')) {
    /**
     * Get current admin authentication token
     * 
     * @return string|null
     */
    function admin_token(): ?string
    {
        return admin_auth()->getToken();
    }
}

if (!function_exists('is_admin_authenticated')) {
    /**
     * Check if admin is authenticated
     * 
     * @return bool
     */
    function is_admin_authenticated(): bool
    {
        return admin_auth()->isAuthenticated();
    }
}
