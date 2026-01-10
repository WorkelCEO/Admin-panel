<?php

namespace App\Services;

use App\DTOs\AdminAuthResponse;
use App\DTOs\ApiResponse;
use App\Exceptions\ApiException;
use App\Exceptions\ApiNotFoundException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * Admin API Service for authentication
 */
class AdminApiService extends BaseApiService
{
    protected function getBaseUrl(): string
    {
        return config('services.admin_api.base_url', env('ADMIN_API_BASE_URL', 'https://your-domain.com/api/admin'));
    }

    protected function getToken(): ?string
    {
        // Get token from session or cache
        return Session::get('admin_api_token') ?? Cache::get('admin_api_token');
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
        Session::put('admin_api_token', $token);
        Cache::put('admin_api_token', $token, $expiresAt ? now()->parse($expiresAt)->diffInSeconds(now()) : 3600);
        
        if ($expiresAt) {
            Session::put('admin_api_token_expires_at', $expiresAt);
            Cache::put('admin_api_token_expires_at', $expiresAt, now()->parse($expiresAt)->diffInSeconds(now()));
        }
    }

    /**
     * Get stored token
     */
    public function getStoredToken(): ?string
    {
        return $this->getToken();
    }

    /**
     * Check if token is expired
     */
    public function isTokenExpired(): bool
    {
        $expiresAt = Session::get('admin_api_token_expires_at') ?? Cache::get('admin_api_token_expires_at');
        
        if (!$expiresAt) {
            return false; // No expiration set
        }

        return now()->greaterThan(now()->parse($expiresAt)->subMinutes(5)); // Refresh 5 minutes before expiration
    }

    /**
     * Clear stored token
     */
    public function clearToken(): void
    {
        Session::forget('admin_api_token');
        Session::forget('admin_api_token_expires_at');
        Cache::forget('admin_api_token');
        Cache::forget('admin_api_token_expires_at');
    }

    /**
     * Admin login
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
            // Don't use token for login endpoint
            $response = $this->postWithoutToken('/auth/login', $data);
            
            \Illuminate\Support\Facades\Log::debug('Admin login API response', [
                'success' => $response->isSuccess(),
                'has_data' => !empty($response->data),
                'has_token' => !empty($response->data['token'] ?? null),
            ]);
            
            $authResponse = AdminAuthResponse::fromApiResponse([
                'data' => $response->data,
                'success' => $response->success,
                'message' => $response->message,
            ]);

            // Store token if login successful
            if ($authResponse->isSuccess() && $authResponse->getToken()) {
                $this->storeToken($authResponse->getToken(), $authResponse->getExpiresAt());
                \Illuminate\Support\Facades\Log::info('Admin token stored successfully');
            } else {
                \Illuminate\Support\Facades\Log::warning('Admin login response missing token', [
                    'success' => $authResponse->isSuccess(),
                    'has_token' => !empty($authResponse->getToken()),
                    'message' => $authResponse->message,
                ]);
            }

            return $authResponse;
        } catch (ApiException $e) {
            \Illuminate\Support\Facades\Log::error('Admin login API exception', [
                'message' => $e->getMessage(),
                'endpoint' => $e->getEndpoint(),
                'context' => $e->getContext(),
            ]);
            
            return AdminAuthResponse::failure($e->getMessage());
        }
    }

    /**
     * Execute POST request without token (for login)
     */
    protected function postWithoutToken(string $endpoint, array $data = []): ApiResponse
    {
        $startTime = microtime(true);
        $fullUrl = $this->baseUrl . $endpoint;
        
        // Log API request (sanitize password in data)
        $sanitizedData = $data;
        if (isset($sanitizedData['password'])) {
            $sanitizedData['password'] = '***REDACTED***';
        }
        
        Log::info("API Request: POST {$fullUrl} (without token)", [
            'service' => $this->getServiceName(),
            'endpoint' => $endpoint,
            'data_keys' => array_keys($data),
            'data_size' => strlen(json_encode($data)),
            'sanitized_data' => $sanitizedData,
        ]);

        if (!$this->circuitBreaker->allowsRequest()) {
            Log::warning("Circuit breaker is open for POST {$fullUrl} (without token)", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
            ]);
            
            throw new \App\Exceptions\ApiConnectionException(
                "Service temporarily unavailable. Circuit breaker is open.",
                $endpoint
            );
        }

        try {
            $client = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout);
            
            // Disable SSL verification in development if configured
            if (config('services.admin_api.verify_ssl', true) === false || env('ADMIN_API_VERIFY_SSL', 'true') === 'false') {
                $client = $client->withoutVerifying();
            }
            
            $response = $client->post($fullUrl, $data);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $statusCode = $response->status();
            $responseSize = strlen($response->body());

            if ($response->successful()) {
                $this->circuitBreaker->recordSuccess();
                $responseData = $response->json();
                
                // Log successful response
                Log::info("API Response: POST {$fullUrl} (without token) - Success", [
                    'service' => $this->getServiceName(),
                    'endpoint' => $endpoint,
                    'status_code' => $statusCode,
                    'duration_ms' => $duration,
                    'response_size_bytes' => $responseSize,
                    'success' => $responseData['success'] ?? true,
                    'has_token' => !empty($responseData['data']['token'] ?? null),
                    'has_data' => !empty($responseData['data']),
                ]);
                
                // Handle Admin API response format
                $data = $responseData['data'] ?? $responseData;
                $meta = $responseData['meta'] ?? [];
                
                // If response has success field, use it
                $success = $responseData['success'] ?? true;
                $message = $responseData['message'] ?? null;
                
                if (!$success) {
                    return ApiResponse::failure($message, $data, $meta);
                }
                
                return ApiResponse::success($data, $meta, $message);
            }

            $this->circuitBreaker->recordFailure();
            
            $responseData = $response->json();
            $errorMessage = $responseData['message'] ?? "POST request failed with status {$statusCode}";
            
            Log::error("API Response: POST {$fullUrl} (without token) - Error", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'duration_ms' => $duration,
                'message' => $errorMessage,
                'response_preview' => substr($response->body(), 0, 500),
            ]);
            
            throw new ApiException(
                $errorMessage,
                $statusCode,
                null,
                $endpoint,
                ['status' => $statusCode, 'body' => $response->body(), 'response_data' => $responseData]
            );
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->circuitBreaker->recordFailure();
            
            Log::error("API Connection Error: POST {$fullUrl} (without token)", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);
            
            throw new \App\Exceptions\ApiConnectionException(
                "Failed to execute POST request: " . $e->getMessage(),
                $endpoint,
                null,
                $e
            );
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->circuitBreaker->recordFailure();
            
            Log::error("API Unexpected Error: POST {$fullUrl} (without token)", [
                'service' => $this->getServiceName(),
                'endpoint' => $endpoint,
                'exception_type' => get_class($e),
                'message' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);
            
            throw $e;
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
            $this->clearToken();
            
            return $response;
        } catch (ApiException $e) {
            // Clear token even if logout fails
            $this->clearToken();
            return ApiResponse::failure($e->getMessage());
        }
    }

    /**
     * Refresh admin token
     */
    public function refreshToken(): AdminAuthResponse
    {
        try {
            $response = $this->post('/auth/refresh', []);
            
            $authResponse = AdminAuthResponse::fromApiResponse([
                'data' => $response->data,
                'success' => $response->success,
                'message' => $response->message,
            ]);

            // Update stored token
            if ($authResponse->isSuccess() && $authResponse->getToken()) {
                $this->storeToken($authResponse->getToken(), $authResponse->getExpiresAt());
            }

            return $authResponse;
        } catch (ApiException $e) {
            // Clear token on refresh failure
            $this->clearToken();
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
     * Override client to include admin token
     */
    protected function client(): PendingRequest
    {
        $client = parent::client();
        
        $token = $this->getToken();
        if ($token) {
            $client->withToken($token);
        }

        return $client;
    }
}
