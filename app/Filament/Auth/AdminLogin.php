<?php

namespace App\Filament\Auth;

use App\Repositories\AdminRepository;
use Filament\Forms\Components\TextInput;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login;
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
                    ],
                ]);

                throw ValidationException::withMessages([
                    'email' => [$errorMessage],
                ]);
            }

            // Store admin user info in session
            $userData = $response->getUser();
            if ($userData) {
                session()->put('admin_user', $userData);
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
                'email' => $data['email'],
                'error' => $e->getMessage(),
                'endpoint' => $e->getEndpoint(),
                'context' => $e->getContext(),
            ]);

            $errorMessage = 'Unable to connect to the Admin API. Please check your configuration or try again later.';
            
            if ($e instanceof \App\Exceptions\ApiConnectionException) {
                $errorMessage = 'Connection failed. Please check if the Admin API is accessible.';
            } elseif ($e instanceof \App\Exceptions\ApiTimeoutException) {
                $errorMessage = 'Request timed out. Please try again.';
            }

            throw ValidationException::withMessages([
                'email' => [$errorMessage],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Unexpected error during admin login', [
                'email' => $data['email'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['An unexpected error occurred. Please try again or contact support.'],
            ]);
        }
    }
}
