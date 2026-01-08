<?php

namespace App\Http\Middleware;

use App\Repositories\AdminRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminApiAuth
{
    public function __construct(
        private AdminRepository $adminRepository
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if token exists
        $token = $this->adminRepository->getStoredToken();
        
        if (!$token) {
            return redirect()->route('filament.admin.auth.login');
        }

        // Check if token is expired and refresh if needed
        if ($this->adminRepository->isTokenExpired()) {
            $refreshResponse = $this->adminRepository->refreshToken();
            
            if (!$refreshResponse->isSuccess()) {
                // Token refresh failed, redirect to login
                return redirect()->route('filament.admin.auth.login');
            }
        }

        return $next($request);
    }
}
