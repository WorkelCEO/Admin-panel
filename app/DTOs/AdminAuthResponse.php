<?php

namespace App\DTOs;

/**
 * Admin authentication response DTO
 */
class AdminAuthResponse extends ApiResponse
{
    public function getUser(): ?array
    {
        if (is_array($this->data)) {
            // Standard location: data.user
            if (isset($this->data['user']) && is_array($this->data['user'])) {
                return $this->data['user'];
            }
            // If user data is at root level, construct user object from available fields
            if (isset($this->data['id']) || isset($this->data['email']) || isset($this->data['name'])) {
                return [
                    'id' => $this->data['id'] ?? null,
                    'email' => $this->data['email'] ?? null,
                    'name' => $this->data['name'] ?? null,
                    'system_role' => $this->data['system_role'] ?? $this->data['role'] ?? null,
                    'is_super_admin' => $this->data['is_super_admin'] ?? false,
                ];
            }
        }
        return null;
    }

    public function getToken(): ?string
    {
        // Check multiple possible token locations
        if (is_array($this->data)) {
            // Standard location: data.token
            if (isset($this->data['token']) && is_string($this->data['token'])) {
                return $this->data['token'];
            }
            // Alternative locations
            if (isset($this->data['access_token']) && is_string($this->data['access_token'])) {
                return $this->data['access_token'];
            }
            if (isset($this->data['auth']['token']) && is_string($this->data['auth']['token'])) {
                return $this->data['auth']['token'];
            }
            if (isset($this->data['user']['token']) && is_string($this->data['user']['token'])) {
                return $this->data['user']['token'];
            }
        }
        return null;
    }

    public function getTokenType(): string
    {
        return $this->data['token_type'] ?? 'Bearer';
    }

    public function getExpiresAt(): ?string
    {
        if (is_array($this->data)) {
            // Standard location: data.expires_at
            if (isset($this->data['expires_at'])) {
                return is_string($this->data['expires_at']) ? $this->data['expires_at'] : (string) $this->data['expires_at'];
            }
            // Alternative: expires_in (convert to expires_at)
            if (isset($this->data['expires_in']) && is_numeric($this->data['expires_in'])) {
                return now()->addSeconds((int) $this->data['expires_in'])->toIso8601String();
            }
            // Check in auth object
            if (isset($this->data['auth']['expires_at'])) {
                return is_string($this->data['auth']['expires_at']) ? $this->data['auth']['expires_at'] : (string) $this->data['auth']['expires_at'];
            }
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
