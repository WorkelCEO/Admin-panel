<?php

namespace App\Filament\Auth;

use App\Repositories\AdminRepository;
use Filament\Forms\Components\TextInput;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Illuminate\Validation\ValidationException;

class AdminLogin extends Login
{
    protected AdminRepository $adminRepository;

    public function boot(AdminRepository $adminRepository): void
    {
        $this->adminRepository = $adminRepository;
    }

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();

        try {
            // Log environment details for debugging server issues
            \Illuminate\Support\Facades\Log::debug('Admin login attempt starting', [
                'email' => $data['email'],
                'environment' => [
                    'app_env' => config('app.env'),
                    'app_url' => config('app.url'),
                    'app_debug' => config('app.debug'),
                    'is_https' => request()->secure(),
                    'session_driver' => config('session.driver'),
                    'session_secure' => config('session.secure'),
                    'session_same_site' => config('session.same_site'),
                    'admin_api_base_url' => config('services.admin_api.base_url'),
                    'admin_api_verify_ssl' => config('services.admin_api.verify_ssl'),
                ],
                'request' => [
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'host' => request()->getHost(),
                    'scheme' => request()->getScheme(),
                ],
            ]);

            $response = $this->adminRepository->login(
                $data['email'],
                $data['password'],
                'Admin Panel - ' . request()->ip()
            );

            if (!$response->isSuccess() || !$response->getToken()) {
                $errorMessage = $response->message ?? 'Invalid credentials. Please check your email and password.';
                
                \Illuminate\Support\Facades\Log::error('Admin login failed', [
                    'email' => $data['email'],
                    'response' => [
                        'success' => $response->isSuccess(),
                        'message' => $response->message,
                        'has_token' => !empty($response->getToken()),
                        'data_keys' => is_array($response->data) ? array_keys($response->data) : [],
                    ],
                    'environment' => [
                        'app_env' => config('app.env'),
                        'app_url' => config('app.url'),
                        'is_https' => request()->secure(),
                        'admin_api_base_url' => config('services.admin_api.base_url'),
                    ],
                ]);

                \Filament\Notifications\Notification::make()
                    ->title('Login failed')
                    ->body($errorMessage)
                    ->danger()
                    ->send();

                throw ValidationException::withMessages([
                    'email' => [$errorMessage],
                ]);
            }

            // Verify token was stored correctly
            $storedToken = $this->adminRepository->getStoredToken();
            \Illuminate\Support\Facades\Log::info('Admin login successful - token verification', [
                'email' => $data['email'],
                'token_received' => !empty($response->getToken()),
                'token_stored' => !empty($storedToken),
                'token_match' => $storedToken === $response->getToken(),
            ]);

            // AdminAuthContext already handles user state synchronization via AdminApiService
            // Verify user state is available
            $authContext = app(\App\Services\Auth\AdminAuthContext::class);
            $userData = $authContext->getUser();
            
            if (!$userData) {
                // If user data not synced, sync it now
                $authContext->syncUserState();
                $userData = $authContext->getUser();
            }

            // Authenticate a Laravel user for Filament
            // Get or create a user based on the admin API response
            $user = \App\Models\User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $userData['name'] ?? $data['email'],
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)), // Random password since we use API auth
                ]
            );

            // Update user name if it changed
            if ($userData && isset($userData['name']) && $user->name !== $userData['name']) {
                $user->name = $userData['name'];
                $user->save();
            }

            // Authenticate the user
            \Illuminate\Support\Facades\Auth::login($user, true); // true = remember me

            \Filament\Notifications\Notification::make()
                ->title('Login successful')
                ->success()
                ->send();

            return app(LoginResponse::class);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\App\Exceptions\ApiException $e) {
            \Illuminate\Support\Facades\Log::error('Admin API error during login', [
                'email' => $data['email'] ?? 'unknown',
                'error' => $e->getMessage(),
                'endpoint' => $e->getEndpoint(),
                'context' => $e->getContext(),
                'exception_type' => get_class($e),
            ]);

            // ErrorHandler already provides user-friendly error messages
            // Use the exception message directly
            $errorMessage = $e->getMessage();

            // Provide fallback for connection errors
            if ($e instanceof \App\Exceptions\ApiConnectionException) {
                $errorMessage = 'Connection failed. Please check if the Admin API is accessible. Verify ADMIN_API_BASE_URL is correct.';
            } elseif ($e instanceof \App\Exceptions\ApiTimeoutException) {
                $errorMessage = 'Request timed out. Please try again.';
            } elseif ($e instanceof \App\Exceptions\ApiNotFoundException) {
                $errorMessage = 'Login endpoint not found. Please verify ADMIN_API_BASE_URL configuration.';
            }

            \Filament\Notifications\Notification::make()
                ->title('Login failed')
                ->body($errorMessage)
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'email' => [$errorMessage],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Unexpected error during admin login', [
                'email' => $data['email'] ?? 'unknown',
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'environment' => [
                    'app_env' => config('app.env'),
                    'app_url' => config('app.url'),
                    'is_https' => request()->secure(),
                    'php_version' => PHP_VERSION,
                ],
            ]);

            $unexpectedError = 'An unexpected error occurred. Please try again or contact support.';

            \Filament\Notifications\Notification::make()
                ->title('Login failed')
                ->body($unexpectedError)
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'email' => [$unexpectedError],
            ]);
        }
    }
}
