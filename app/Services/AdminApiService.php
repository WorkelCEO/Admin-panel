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
            // Log the exact data being sent (sanitize password)
            $logData = $data;
            if (isset($logData['password'])) {
                $logData['password'] = '***REDACTED***';
            }
            \Illuminate\Support\Facades\Log::debug('Admin login request data', [
                'email' => $email,
                'has_password' => !empty($data['password']),
                'password_length' => strlen($data['password'] ?? ''),
                'device_name' => $deviceName,
                'data_keys' => array_keys($data),
                'sanitized_data' => $logData,
            ]);
            
            // Don't use token for login endpoint
            $response = $this->postWithoutToken('/auth/login', $data);
            
            // Log detailed response structure for debugging
            $responseDataForLog = $response->data;
            if (is_array($responseDataForLog)) {
                // Sanitize token for logging
                if (isset($responseDataForLog['token'])) {
                    $responseDataForLog['token'] = substr($responseDataForLog['token'], 0, 20) . '...';
                }
                if (isset($responseDataForLog['user']['token'])) {
                    $responseDataForLog['user']['token'] = substr($responseDataForLog['user']['token'], 0, 20) . '...';
                }
            }
            
            \Illuminate\Support\Facades\Log::debug('Admin login API response', [
                'success' => $response->isSuccess(),
                'has_data' => !empty($response->data),
                'data_type' => gettype($response->data),
                'data_keys' => is_array($response->data) ? array_keys($response->data) : [],
                'has_token' => !empty($response->data['token'] ?? null),
                'has_token_in_user' => !empty($response->data['user']['token'] ?? null),
                'response_data' => $responseDataForLog,
            ]);
            
            $authResponse = AdminAuthResponse::fromApiResponse([
                'data' => $response->data,
                'success' => $response->success,
                'message' => $response->message,
            ]);
            
            // Log what we extracted
            \Illuminate\Support\Facades\Log::debug('AdminAuthResponse created', [
                'success' => $authResponse->isSuccess(),
                'has_token' => !empty($authResponse->getToken()),
                'has_user' => !empty($authResponse->getUser()),
                'token_preview' => $authResponse->getToken() ? substr($authResponse->getToken(), 0, 20) . '...' : null,
                'data_structure' => is_array($authResponse->data) ? array_keys($authResponse->data) : [],
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

            $message = $e->getMessage();
            $ctx = $e->getContext() ?? [];

            // 500: detect database configuration errors on the API
            if ($e->getCode() === 500) {
                $body = (string) ($ctx['body'] ?? '');
                $responseData = $ctx['response_data'] ?? [];
                $debug = (string) ($responseData['debug'] ?? '');
                $apiErrorMessage = (string) ($responseData['message'] ?? $body);
                $exceptionMessage = (string) $e->getMessage();
                
                // Combine all possible error message sources for detection
                $combinedMessage = $exceptionMessage . ' ' . $apiErrorMessage . ' ' . $body;
                
                // Detect SQLite database path configuration error
                if (str_contains($combinedMessage, 'Database file at path') && 
                    (str_contains($combinedMessage, 'does not exist') || str_contains($combinedMessage, 'Ensure this is an absolute path'))) {
                    $message = 'The Admin API database configuration is incorrect. The SQLite database path is invalid. Please check the DB_DATABASE setting in the API server\'s .env file and ensure it contains an absolute path to the database file (e.g., /path/to/database.sqlite).';
                }
                // Detect missing users.deleted_at column
                elseif (str_contains($combinedMessage, 'deleted_at')) {
                    $message = 'The Admin API database is missing the users.deleted_at column. On the API backend, add a migration with $table->softDeletes() on the users table and run php artisan migrate.';
                }
                // Detect other database connection errors
                elseif (str_contains($combinedMessage, 'SQLSTATE') || 
                        str_contains($combinedMessage, 'Connection') || 
                        (str_contains($combinedMessage, 'database') && str_contains($combinedMessage, 'does not exist'))) {
                    $message = 'The Admin API database connection failed. Please check the database configuration on the API server (DB_CONNECTION, DB_DATABASE, DB_HOST, etc. in .env file).';
                }
            }
            // 403: handle insufficient permissions
            elseif ($e->getCode() === 403) {
                $responseData = $ctx['response_data'] ?? [];
                $errorData = $responseData['data'] ?? [];
                $currentRole = $errorData['current_role'] ?? 'unknown';
                $requiredRoles = $errorData['required_roles'] ?? [];
                
                if (!empty($requiredRoles)) {
                    $rolesList = implode(' or ', $requiredRoles);
                    $message = "Access denied. Your account has the '{$currentRole}' role, but you need {$rolesList} privileges to access the admin panel. Please contact an administrator to upgrade your account.";
                } else {
                    $message = $responseData['message'] ?? $e->getMessage();
                }
            }
            // 422: use API validation errors (e.g. "The selected email is invalid.")
            elseif ($e->getCode() === 422) {
                $errors = $ctx['response_data']['errors'] ?? [];
                $message = $errors['email'][0] ?? $errors['password'][0] ?? null;
                if ($message === null && !empty($errors)) {
                    $first = reset($errors);
                    $message = is_array($first) ? ($first[0] ?? $message) : $first;
                }
                $message = $message ?? $e->getMessage();
            }

            return AdminAuthResponse::failure($message);
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
            // Use minimal headers to match curl exactly (curl works, so let's match it)
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];
            
            Log::debug("Login request headers", [
                'headers' => $headers,
                'full_url' => $fullUrl,
            ]);
            
            // Log the exact JSON being sent
            $jsonData = json_encode($data, JSON_UNESCAPED_SLASHES);
            Log::debug("Login request JSON body", [
                'json' => $jsonData,
                'json_length' => strlen($jsonData),
                'data' => $data,
            ]);
            
            $client = Http::withHeaders($headers)->timeout($this->timeout);
            
            // Disable SSL verification in development if configured
            $verifySsl = config('services.admin_api.verify_ssl', true);
            $envVerifySsl = env('ADMIN_API_VERIFY_SSL', 'true');
            
            // Handle boolean and string values
            if ($verifySsl === false || $verifySsl === 'false' || 
                ($envVerifySsl !== null && ($envVerifySsl === false || $envVerifySsl === 'false'))) {
                $client = $client->withoutVerifying();
                Log::debug("SSL verification disabled for POST {$fullUrl}", [
                    'verify_ssl_config' => $verifySsl,
                    'verify_ssl_env' => $envVerifySsl,
                ]);
            }
            
            // Send the request - Laravel automatically JSON encodes arrays when Content-Type is application/json
            // This matches curl behavior exactly
            $response = $client->post($fullUrl, $data);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $statusCode = $response->status();
            $responseSize = strlen($response->body());

            if ($response->successful()) {
                $this->circuitBreaker->recordSuccess();
                $responseData = $response->json();
                
                // Log the actual response structure for debugging (sanitize sensitive data)
                $logData = $responseData;
                if (isset($logData['data']['token'])) {
                    $logData['data']['token'] = substr($logData['data']['token'], 0, 20) . '...';
                }
                if (isset($logData['token'])) {
                    $logData['token'] = substr($logData['token'], 0, 20) . '...';
                }
                
                Log::debug("API Response structure: POST {$fullUrl}", [
                    'service' => $this->getServiceName(),
                    'endpoint' => $endpoint,
                    'response_keys' => array_keys($responseData ?? []),
                    'response_structure' => $logData,
                    'raw_body_preview' => substr($response->body(), 0, 500),
                ]);
                
                // Handle different Admin API response formats
                // Format 1: { success: true, data: { token: "...", user: {...} } }
                // Format 2: { token: "...", user: {...} } (data at root level)
                // Format 3: { success: true, message: "...", token: "...", user: {...} }
                
                $data = [];
                $success = true;
                $message = null;
                
                // Extract success and message first
                $success = $responseData['success'] ?? true;
                $message = $responseData['message'] ?? null;
                
                // Check if data is in 'data' wrapper (most common format)
                if (isset($responseData['data'])) {
                    if (is_array($responseData['data'])) {
                        $data = $responseData['data'];
                    } else {
                        // If data is not an array, wrap it
                        $data = ['value' => $responseData['data']];
                    }
                } 
                // Check if token/user/auth fields are at root level (direct response)
                elseif (isset($responseData['token']) || isset($responseData['user']) || isset($responseData['auth'])) {
                    // Extract auth-related fields at root level
                    $data = [];
                    if (isset($responseData['token'])) $data['token'] = $responseData['token'];
                    if (isset($responseData['token_type'])) $data['token_type'] = $responseData['token_type'];
                    if (isset($responseData['expires_at'])) $data['expires_at'] = $responseData['expires_at'];
                    if (isset($responseData['expires_in'])) $data['expires_at'] = now()->addSeconds($responseData['expires_in'])->toIso8601String();
                    if (isset($responseData['user'])) $data['user'] = $responseData['user'];
                    if (isset($responseData['auth'])) {
                        if (is_array($responseData['auth'])) {
                            $data = array_merge($data, $responseData['auth']);
                        } else {
                            $data['auth'] = $responseData['auth'];
                        }
                    }
                    // Include any other fields that might be useful
                    foreach (['name', 'email', 'id', 'role'] as $field) {
                        if (isset($responseData[$field]) && !isset($data[$field])) {
                            if (!isset($data['user'])) $data['user'] = [];
                            $data['user'][$field] = $responseData[$field];
                        }
                    }
                }
                // Fallback: use entire response as data (filter out metadata fields)
                else {
                    $data = $responseData;
                    // Remove metadata fields to keep only actual data
                    unset($data['success'], $data['message'], $data['meta'], $data['status'], $data['code']);
                }
                
                // Log successful response
                Log::info("API Response: POST {$fullUrl} (without token) - Success", [
                    'service' => $this->getServiceName(),
                    'endpoint' => $endpoint,
                    'status_code' => $statusCode,
                    'duration_ms' => $duration,
                    'response_size_bytes' => $responseSize,
                    'success' => $success,
                    'has_token' => !empty($data['token'] ?? null),
                    'has_data' => !empty($data),
                    'data_keys' => is_array($data) ? array_keys($data) : [],
                ]);
                
                if (!$success) {
                    return ApiResponse::failure($message, $data, []);
                }
                
                return ApiResponse::success($data, [], $message);
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
     * Uses postWithoutToken to avoid recursive 401 handling
     */
    public function refreshToken(): AdminAuthResponse
    {
        try {
            // Use postWithoutToken to avoid triggering handleUnauthorized recursively
            // But we still need to include the current token for refresh
            $startTime = microtime(true);
            $fullUrl = $this->baseUrl . '/auth/refresh';
            
            Log::info("API Request: POST {$fullUrl} (token refresh)", [
                'service' => $this->getServiceName(),
                'endpoint' => '/auth/refresh',
            ]);

            $token = $this->getToken();
            if (!$token) {
                Log::warning("Token refresh attempted but no token available");
                $this->clearToken();
                return AdminAuthResponse::failure("No token available to refresh");
            }

            $client = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->timeout($this->timeout);
            
            // Disable SSL verification in development if configured
            $verifySsl = config('services.admin_api.verify_ssl', true);
            $envVerifySsl = env('ADMIN_API_VERIFY_SSL', 'true');
            
            if ($verifySsl === false || $verifySsl === 'false' || 
                ($envVerifySsl !== null && ($envVerifySsl === false || $envVerifySsl === 'false'))) {
                $client = $client->withoutVerifying();
            }
            
            $response = $client->post($fullUrl, []);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $statusCode = $response->status();

            if ($response->successful()) {
                $this->circuitBreaker->recordSuccess();
                $responseData = $response->json();
                
                $data = [];
                $success = true;
                $message = null;
                
                $success = $responseData['success'] ?? true;
                $message = $responseData['message'] ?? null;
                
                if (isset($responseData['data'])) {
                    $data = is_array($responseData['data']) ? $responseData['data'] : ['value' => $responseData['data']];
                } elseif (isset($responseData['token']) || isset($responseData['user'])) {
                    if (isset($responseData['token'])) $data['token'] = $responseData['token'];
                    if (isset($responseData['token_type'])) $data['token_type'] = $responseData['token_type'];
                    if (isset($responseData['expires_at'])) $data['expires_at'] = $responseData['expires_at'];
                    if (isset($responseData['expires_in'])) $data['expires_at'] = now()->addSeconds($responseData['expires_in'])->toIso8601String();
                    if (isset($responseData['user'])) $data['user'] = $responseData['user'];
                } else {
                    $data = $responseData;
                    unset($data['success'], $data['message'], $data['meta'], $data['status'], $data['code']);
                }
                
                $authResponse = AdminAuthResponse::fromApiResponse([
                    'data' => $data,
                    'success' => $success,
                    'message' => $message,
                ]);

                // Update stored token
                if ($authResponse->isSuccess() && $authResponse->getToken()) {
                    $this->storeToken($authResponse->getToken(), $authResponse->getExpiresAt());
                    Log::info("Token refreshed successfully", [
                        'service' => $this->getServiceName(),
                        'duration_ms' => $duration,
                    ]);
                }

                return $authResponse;
            }

            // Refresh failed
            $this->circuitBreaker->recordSuccess(); // 401/403 on refresh is not a service failure
            $responseData = $response->json() ?? [];
            $errorMessage = $responseData['message'] ?? "Token refresh failed with status {$statusCode}";
            
            Log::warning("Token refresh failed", [
                'service' => $this->getServiceName(),
                'status_code' => $statusCode,
                'duration_ms' => $duration,
                'message' => $errorMessage,
            ]);
            
            $this->clearToken();
            return AdminAuthResponse::failure($errorMessage);
            
        } catch (\Exception $e) {
            // Clear token on any exception during refresh
            Log::error("Token refresh exception", [
                'service' => $this->getServiceName(),
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
            ]);
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

    /**
     * Handle unauthorized (401) response by logging out the user
     */
    protected function handleUnauthorized(string $endpoint, array $responseData = []): bool
    {
        // Don't logout on auth endpoints
        if (str_contains($endpoint, '/auth/login') || str_contains($endpoint, '/auth/refresh') || str_contains($endpoint, '/auth/logout')) {
            return false;
        }

        Log::warning("API Unauthorized: Logging out user due to 401 error", [
            'service' => $this->getServiceName(),
            'endpoint' => $endpoint,
        ]);

        $this->logoutUser();
        return false;
    }

    /**
     * Logout the user and clear all authentication data
     * Made public so other services can call it
     */
    public function logoutUser(): void
    {
        try {
            // Clear token
            $this->clearToken();
            
            // Clear admin user from session
            Session::forget('admin_user');
            
            // Logout Filament user if authenticated
            if (\Illuminate\Support\Facades\Auth::check()) {
                \Illuminate\Support\Facades\Auth::logout();
                Session::invalidate();
                Session::regenerateToken();
            }
            
            Log::info("User logged out due to authentication failure", [
                'service' => $this->getServiceName(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error during user logout", [
                'service' => $this->getServiceName(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
