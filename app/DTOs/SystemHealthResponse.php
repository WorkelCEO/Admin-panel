<?php

namespace App\DTOs;

/**
 * System health DTO
 */
class SystemHealthResponse extends ApiResponse
{
    public function getDatabase(): ?string
    {
        return $this->data['database'] ?? null;
    }

    public function getCache(): ?string
    {
        return $this->data['cache'] ?? null;
    }

    public function getRedis(): ?string
    {
        return $this->data['redis'] ?? null;
    }

    public function getStorage(): ?string
    {
        return $this->data['storage'] ?? null;
    }

    public function getLogs(): ?string
    {
        return $this->data['logs'] ?? null;
    }

    public function isHealthy(): bool
    {
        return $this->getDatabase() === 'connected' &&
               $this->getCache() === 'connected' &&
               $this->getStorage() === 'writable' &&
               $this->getLogs() === 'writable';
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
}
