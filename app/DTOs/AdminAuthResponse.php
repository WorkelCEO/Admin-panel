<?php

namespace App\DTOs;

/**
 * Admin authentication response DTO
 */
class AdminAuthResponse extends ApiResponse
{
    public function getUser(): ?array
    {
        return $this->data['user'] ?? null;
    }

    public function getToken(): ?string
    {
        return $this->data['token'] ?? null;
    }

    public function getTokenType(): string
    {
        return $this->data['token_type'] ?? 'Bearer';
    }

    public function getExpiresAt(): ?string
    {
        return $this->data['expires_at'] ?? null;
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
