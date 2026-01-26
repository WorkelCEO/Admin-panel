<?php

namespace App\DTOs;

/**
 * Admin authentication response DTO
 * 
 * Matches API documentation structure:
 * {
 *   "success": true,
 *   "message": "Admin login successful.",
 *   "data": {
 *     "user": {...},
 *     "token": "...",
 *     "token_type": "Bearer",
 *     "expires_at": "..."
 *   }
 * }
 */
class AdminAuthResponse extends ApiResponse
{
    /**
     * Get user data from response
     * ResponseParser ensures data.user is available
     */
    public function getUser(): ?array
    {
        if (is_array($this->data) && isset($this->data['user']) && is_array($this->data['user'])) {
            return $this->data['user'];
        }
        return null;
    }

    /**
     * Get authentication token
     * ResponseParser ensures data.token is available
     */
    public function getToken(): ?string
    {
        if (is_array($this->data) && isset($this->data['token']) && is_string($this->data['token'])) {
            return $this->data['token'];
        }
        return null;
    }

    /**
     * Get token type (defaults to Bearer)
     */
    public function getTokenType(): string
    {
        return $this->data['token_type'] ?? 'Bearer';
    }

    /**
     * Get token expiration time
     * ResponseParser handles expires_in conversion to expires_at
     */
    public function getExpiresAt(): ?string
    {
        if (is_array($this->data) && isset($this->data['expires_at'])) {
            return is_string($this->data['expires_at']) 
                ? $this->data['expires_at'] 
                : (string) $this->data['expires_at'];
        }
        return null;
    }

    public static function fromApiResponse(array $response): self
    {
        return new self(
            $response['data'] ?? [],
            [],
            $response['success'] ?? true,
            $response['message'] ?? null
        );
    }

    public static function failure(?string $message = null, mixed $data = null, array $meta = []): self
    {
        return new self($data ?? [], $meta, false, $message);
    }
}
