<?php

namespace App\Services\Http;

use App\Exceptions\ApiConnectionException;
use App\Exceptions\ApiException;
use App\Exceptions\ApiNotFoundException;
use App\Exceptions\ApiRateLimitException;
use App\Exceptions\ApiTimeoutException;
use Illuminate\Http\Client\Response;

/**
 * Error Handler - Maps HTTP errors to appropriate exceptions
 */
class ErrorHandler
{
    public function __construct(
        private ResponseParser $responseParser
    ) {
    }

    /**
     * Handle HTTP error response and throw appropriate exception
     */
    public function handleError(Response $response, string $endpoint): never
    {
        $statusCode = $response->status();
        $responseJson = $response->json() ?? [];
        $responseBody = $response->body();

        match ($statusCode) {
            401 => $this->handleUnauthorized($responseJson, $endpoint, $responseBody),
            403 => $this->handleForbidden($responseJson, $endpoint, $responseBody),
            404 => $this->handleNotFound($endpoint, $responseBody),
            422 => $this->handleValidationError($responseJson, $endpoint, $responseBody),
            429 => $this->handleRateLimit($response, $endpoint, $responseBody),
            500, 502, 503, 504 => $this->handleServerError($responseJson, $statusCode, $endpoint, $responseBody),
            default => $this->handleGenericError($responseJson, $statusCode, $endpoint, $responseBody),
        };
    }

    /**
     * Handle 401 Unauthorized
     */
    private function handleUnauthorized(array $responseJson, string $endpoint, string $responseBody): never
    {
        $message = $this->responseParser->extractErrorMessage($responseJson, 'Unauthenticated. Please log in again.');

        throw new ApiException(
            $message,
            401,
            null,
            $endpoint,
            ['status' => 401, 'body' => $responseBody, 'response_data' => $responseJson, 'requires_auth' => true]
        );
    }

    /**
     * Handle 403 Forbidden
     */
    private function handleForbidden(array $responseJson, string $endpoint, string $responseBody): never
    {
        $message = $this->responseParser->extractErrorMessage($responseJson, 'Access denied.');
        $errorData = $responseJson['data'] ?? [];
        $currentRole = $errorData['current_role'] ?? 'unknown';
        $requiredRoles = $errorData['required_roles'] ?? [];

        // Provide detailed message for insufficient permissions
        if (!empty($requiredRoles)) {
            $rolesList = implode(' or ', $requiredRoles);
            $message = "Access denied. Your account has the '{$currentRole}' role, but you need {$rolesList} privileges.";
        }

        throw new ApiException(
            $message,
            403,
            null,
            $endpoint,
            [
                'status' => 403,
                'body' => $responseBody,
                'response_data' => $responseJson,
                'current_role' => $currentRole,
                'required_roles' => $requiredRoles,
            ]
        );
    }

    /**
     * Handle 404 Not Found
     */
    private function handleNotFound(string $endpoint, string $responseBody): never
    {
        throw new ApiNotFoundException(
            'Resource not found',
            $endpoint,
            ['status' => 404, 'body' => $responseBody]
        );
    }

    /**
     * Handle 422 Validation Error
     */
    private function handleValidationError(array $responseJson, string $endpoint, string $responseBody): never
    {
        $errors = $this->responseParser->extractValidationErrors($responseJson);
        $message = $this->responseParser->extractErrorMessage($responseJson, 'Validation failed.');

        // Extract first error message for user-friendly display
        $firstError = $this->getFirstErrorMessage($errors);
        if ($firstError) {
            $message = $firstError;
        }

        throw new ApiException(
            $message,
            422,
            null,
            $endpoint,
            ['status' => 422, 'body' => $responseBody, 'response_data' => $responseJson, 'errors' => $errors]
        );
    }

    /**
     * Handle 429 Rate Limit
     */
    private function handleRateLimit(Response $response, string $endpoint, string $responseBody): never
    {
        $retryAfterHeader = $response->header('Retry-After');
        $retryAfter = $retryAfterHeader ? (int) $retryAfterHeader : 60;

        throw new ApiRateLimitException(
            'Rate limit exceeded',
            $endpoint,
            $retryAfter,
            ['status' => 429, 'body' => $responseBody, 'retry_after' => $retryAfter]
        );
    }

    /**
     * Handle 500+ Server Errors
     */
    private function handleServerError(array $responseJson, int $statusCode, string $endpoint, string $responseBody): never
    {
        $message = $this->responseParser->extractErrorMessage($responseJson, 'Server error occurred.');
        $errorCode = $this->responseParser->extractErrorCode($responseJson);

        // Detect specific database configuration errors
        $message = $this->detectDatabaseErrors($message, $responseJson, $responseBody);

        throw new ApiException(
            $message,
            $statusCode,
            null,
            $endpoint,
            [
                'status' => $statusCode,
                'body' => $responseBody,
                'response_data' => $responseJson,
                'error_code' => $errorCode,
            ]
        );
    }

    /**
     * Handle generic errors
     */
    private function handleGenericError(array $responseJson, int $statusCode, string $endpoint, string $responseBody): never
    {
        $message = $this->responseParser->extractErrorMessage($responseJson, "Request failed with status {$statusCode}");

        throw new ApiException(
            $message,
            $statusCode,
            null,
            $endpoint,
            ['status' => $statusCode, 'body' => $responseBody, 'response_data' => $responseJson]
        );
    }

    /**
     * Extract first error message from validation errors
     */
    private function getFirstErrorMessage(array $errors): ?string
    {
        if (empty($errors)) {
            return null;
        }

        foreach ($errors as $field => $messages) {
            if (is_array($messages) && !empty($messages)) {
                return $messages[0];
            }
            if (is_string($messages)) {
                return $messages;
            }
        }

        return null;
    }

    /**
     * Detect and provide user-friendly messages for database errors
     */
    private function detectDatabaseErrors(string $message, array $responseJson, string $responseBody): string
    {
        $debug = (string) ($responseJson['debug'] ?? '');
        $apiMessage = (string) ($responseJson['message'] ?? '');
        $combinedMessage = $message . ' ' . $apiMessage . ' ' . $responseBody . ' ' . $debug;

        // SQLite database path configuration error
        if (str_contains($combinedMessage, 'Database file at path') &&
            (str_contains($combinedMessage, 'does not exist') || str_contains($combinedMessage, 'Ensure this is an absolute path'))) {
            return 'The Admin API database configuration is incorrect. The SQLite database path is invalid. Please check the DB_DATABASE setting in the API server\'s .env file and ensure it contains an absolute path to the database file (e.g., /path/to/database.sqlite).';
        }

        // Missing deleted_at column
        if (str_contains($combinedMessage, 'deleted_at')) {
            return 'The Admin API database is missing the users.deleted_at column. On the API backend, add a migration with $table->softDeletes() on the users table and run php artisan migrate.';
        }

        // Database connection errors
        if (str_contains($combinedMessage, 'SQLSTATE') ||
            str_contains($combinedMessage, 'Connection') ||
            (str_contains($combinedMessage, 'database') && str_contains($combinedMessage, 'does not exist'))) {
            return 'The Admin API database connection failed. Please check the database configuration on the API server (DB_CONNECTION, DB_DATABASE, DB_HOST, etc. in .env file).';
        }

        return $message;
    }
}
