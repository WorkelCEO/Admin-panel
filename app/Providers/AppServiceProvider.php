<?php

namespace App\Providers;

use App\Services\Auth\AdminAuthContext;
use App\Services\TokenManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register TokenManager as singleton to ensure consistent state
        $this->app->singleton(TokenManager::class, function ($app) {
            return new TokenManager();
        });

        // Register AdminAuthContext as singleton
        // AdminApiService is loaded lazily to avoid circular dependency
        $this->app->singleton(AdminAuthContext::class, function ($app) {
            return new AdminAuthContext(
                $app->make(TokenManager::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
