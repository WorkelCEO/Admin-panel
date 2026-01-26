<?php

namespace App\Services\Http;

use App\DTOs\ApiResponse;

/**
 * Response Parser - Parses API responses according to documented structure
 * 
 * Expected format: { success: bool, message: string, data: {...} }
 */
class ResponseParser
{
    /**
     * Parse API response to ApiResponse DTO
     */
    public function parse(array $responseJson): ApiResponse
    {
        $success = $responseJson['success'] ?? true;
        $message = $responseJson['message'] ?? null;
        $data = $this->extractData($responseJson);

        return new ApiResponse($data, [], $success, $message);
    }

    /**
     * Extract data from response
     * Handles multiple response formats:
     * 1. { success: true, data: {...} } - Standard format
     * 2. { success: true, token: "...", user: {...} } - Auth response at root
     * 3. { token: "...", user: {...} } - Direct auth response
     */
    private function extractData(array $responseJson): array
    {
        // Standard format: data is in 'data' key
        if (isset($responseJson['data']) && is_array($responseJson['data'])) {
            return $responseJson['data'];
        }

        // Auth response format: token/user at root level
        if (isset($responseJson['token']) || isset($responseJson['user'])) {
            return $this->extractAuthData($responseJson);
        }

        // Fallback: use entire response but remove metadata fields
        $data = $responseJson;
        unset($data['success'], $data['message'], $data['meta'], $data['status'], $data['code']);

        return $data;
    }

    /**
     * Extract authentication data from response
     */
    private function extractAuthData(array $responseJson): array
    {
        $data = [];

        // Extract token and related fields
        if (isset($responseJson['token'])) {
            $data['token'] = $responseJson['token'];
        }
        if (isset($responseJson['token_type'])) {
            $data['token_type'] = $responseJson['token_type'];
        }
        if (isset($responseJson['expires_at'])) {
            $data['expires_at'] = $responseJson['expires_at'];
        }
        if (isset($responseJson['expires_in'])) {
            $data['expires_at'] = now()->addSeconds((int) $responseJson['expires_in'])->toIso8601String();
        }

        // Extract user data
        if (isset($responseJson['user']) && is_array($responseJson['user'])) {
            $data['user'] = $responseJson['user'];
        } elseif (isset($responseJson['id']) || isset($responseJson['email'])) {
            // User data at root level
            $data['user'] = [
                'id' => $responseJson['id'] ?? null,
                'email' => $responseJson['email'] ?? null,
                'name' => $responseJson['name'] ?? null,
                'system_role' => $responseJson['system_role'] ?? $responseJson['role'] ?? null,
                'is_super_admin' => $responseJson['is_super_admin'] ?? false,
            ];
        }

        return $data;
    }

    /**
     * Extract validation errors from 422 response
     */
    public function extractValidationErrors(array $responseJson): array
    {
        return $responseJson['errors'] ?? [];
    }

    /**
     * Extract error message from response
     */
    public function extractErrorMessage(array $responseJson, ?string $default = null): string
    {
        return $responseJson['message'] ?? $default ?? 'An error occurred';
    }

    /**
     * Extract error code from response
     */
    public function extractErrorCode(array $responseJson): ?string
    {
        return $responseJson['error_code'] ?? null;
    }
}
