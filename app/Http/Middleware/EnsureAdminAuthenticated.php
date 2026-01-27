<?php

namespace App\Http\Middleware;

use App\Services\Auth\AdminAuthContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure Admin Authenticated Middleware
 * 
 * Proactively manages authentication state:
 * - Checks if user is authenticated
 * - Refreshes token if expiring soon
 * - Synchronizes user state
 * - Redirects to login if authentication fails
 */
class EnsureAdminAuthenticated
{
    public function __construct(
        private AdminAuthContext $authContext
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!$this->authContext->isAuthenticated()) {
            $this->authContext->clearAuth();
            return redirect()->route('filament.admin.auth.login');
        }

        // Proactively refresh token if needed
        try {
            $this->authContext->refreshTokenIfNeeded();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Token refresh failed in middleware', [
                'error' => $e->getMessage(),
            ]);
            
            // If refresh fails, clear auth and redirect
            $this->authContext->clearAuth();
            return redirect()->route('filament.admin.auth.login');
        }

        // Ensure user state is synchronized
        if (!$this->authContext->getUser()) {
            $this->authContext->syncUserState();
        }

        return $next($request);
    }
}
