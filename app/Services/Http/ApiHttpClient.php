<?php

namespace App\Services\Http;

use App\DTOs\ApiResponse;
use App\Exceptions\ApiConnectionException;
use App\Services\CircuitBreaker;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * API HTTP Client - Handles all HTTP communication with the API
 */
class ApiHttpClient
{
    private string $baseUrl;
    private CircuitBreaker $circuitBreaker;
    private ResponseParser $responseParser;
    private ErrorHandler $errorHandler;
    private RetryHandler $retryHandler;
    private string $serviceName;
    private bool $verifySsl;

    public function __construct(
        string $baseUrl,
        string $serviceName,
        CircuitBreaker $circuitBreaker,
        ResponseParser $responseParser,
        ErrorHandler $errorHandler,
        RetryHandler $retryHandler,
        bool $verifySsl = true
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->serviceName = $serviceName;
        $this->circuitBreaker = $circuitBreaker;
        $this->responseParser = $responseParser;
        $this->errorHandler = $errorHandler;
        $this->retryHandler = $retryHandler;
        $this->verifySsl = $verifySsl;
    }

    /**
     * Execute GET request
     */
    public function get(string $endpoint, array $params = [], ?string $token = null): ApiResponse
    {
        $fullUrl = $this->buildUrl($endpoint);
        
        $this->logRequest('GET', $fullUrl, ['params' => $this->sanitizeParams($params)]);

        $this->checkCircuitBreaker($endpoint);

        return $this->retryHandler->execute(
            function ($timeout) use ($fullUrl, $params, $token) {
                $response = $this->createClient($token, $timeout)
                    ->get($fullUrl, $params);

                return $this->handleResponse($response, $endpoint);
            },
            $endpoint
        );
    }

    /**
     * Execute POST request
     */
    public function post(string $endpoint, array $data = [], ?string $token = null): ApiResponse
    {
        $fullUrl = $this->buildUrl($endpoint);
        
        $this->logRequest('POST', $fullUrl, ['data_keys' => array_keys($data)]);

        $this->checkCircuitBreaker($endpoint);

        return $this->retryHandler->execute(
            function ($timeout) use ($fullUrl, $data, $token) {
                $response = $this->createClient($token, $timeout)
                    ->post($fullUrl, $data);

                return $this->handleResponse($response, $endpoint);
            },
            $endpoint
        );
    }

    /**
     * Execute PUT request
     */
    public function put(string $endpoint, array $data = [], ?string $token = null): ApiResponse
    {
        $fullUrl = $this->buildUrl($endpoint);
        
        $this->logRequest('PUT', $fullUrl, ['data_keys' => array_keys($data)]);

        $this->checkCircuitBreaker($endpoint);

        return $this->retryHandler->execute(
            function ($timeout) use ($fullUrl, $data, $token) {
                $response = $this->createClient($token, $timeout)
                    ->put($fullUrl, $data);

                return $this->handleResponse($response, $endpoint);
            },
            $endpoint
        );
    }

    /**
     * Execute DELETE request
     */
    public function delete(string $endpoint, ?string $token = null): bool
    {
        $fullUrl = $this->buildUrl($endpoint);
        
        $this->logRequest('DELETE', $fullUrl);

        $this->checkCircuitBreaker($endpoint);

        try {
            return $this->retryHandler->execute(
                function ($timeout) use ($fullUrl, $token, $endpoint) {
                    $response = $this->createClient($token, $timeout)
                        ->delete($fullUrl);

                    if ($response->successful()) {
                        $this->circuitBreaker->recordSuccess();
                        $this->logResponse('DELETE', $fullUrl, $response->status());
                        return true;
                    }

                    // Handle error (throws exception)
                    $this->circuitBreaker->recordFailure();
                    $this->errorHandler->handleError($response, $endpoint);
                },
                $endpoint
            );
        } catch (\App\Exceptions\ApiException $e) {
            // Re-throw API exceptions
            throw $e;
        }
    }

    /**
     * Handle HTTP response
     */
    private function handleResponse(Response $response, string $endpoint): ApiResponse
    {
        $statusCode = $response->status();

        if ($response->successful()) {
            $this->circuitBreaker->recordSuccess();
            
            $responseJson = $response->json() ?? [];
            $apiResponse = $this->responseParser->parse($responseJson);
            
            $this->logResponse('SUCCESS', $endpoint, $statusCode);
            
            return $apiResponse;
        }

        // Handle error responses
        $this->circuitBreaker->recordFailure();
        $this->errorHandler->handleError($response, $endpoint);
    }

    /**
     * Create HTTP client with authentication and configuration
     */
    private function createClient(?string $token, int $timeout): PendingRequest
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $client = Http::withHeaders($headers)->timeout($timeout);

        if ($token) {
            $client = $client->withToken($token);
        }

        if (!$this->verifySsl) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    /**
     * Build full URL from endpoint
     */
    private function buildUrl(string $endpoint): string
    {
        $endpoint = ltrim($endpoint, '/');
        return "{$this->baseUrl}/{$endpoint}";
    }

    /**
     * Check circuit breaker before making request
     */
    private function checkCircuitBreaker(string $endpoint): void
    {
        if (!$this->circuitBreaker->allowsRequest()) {
            throw new ApiConnectionException(
                "Service temporarily unavailable. Circuit breaker is open.",
                $endpoint
            );
        }
    }

    /**
     * Log request
     */
    private function logRequest(string $method, string $url, array $context = []): void
    {
        Log::info("API Request: {$method} {$url}", array_merge([
            'service' => $this->serviceName,
        ], $context));
    }

    /**
     * Log response
     */
    private function logResponse(string $method, string $endpoint, int $statusCode): void
    {
        Log::info("API Response: {$method} {$endpoint}", [
            'service' => $this->serviceName,
            'status_code' => $statusCode,
        ]);
    }

    /**
     * Sanitize parameters for logging
     */
    private function sanitizeParams(array $params): array
    {
        $sensitiveKeys = ['password', 'password_confirmation', 'token', 'api_key', 'secret', 'access_token', 'refresh_token'];
        $sanitized = $params;

        foreach ($sensitiveKeys as $key) {
            if (isset($sanitized[$key])) {
                $sanitized[$key] = '***REDACTED***';
            }
        }

        return $sanitized;
    }
}
