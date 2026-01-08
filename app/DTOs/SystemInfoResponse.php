<?php

namespace App\DTOs;

/**
 * System information DTO
 */
class SystemInfoResponse extends ApiResponse
{
    public function getLaravelVersion(): ?string
    {
        return $this->data['laravel_version'] ?? null;
    }

    public function getPhpVersion(): ?string
    {
        return $this->data['php_version'] ?? null;
    }

    public function getEnvironment(): ?string
    {
        return $this->data['environment'] ?? null;
    }

    public function isDebugMode(): bool
    {
        return $this->data['debug_mode'] ?? false;
    }

    public function getTimezone(): ?string
    {
        return $this->data['timezone'] ?? null;
    }

    public function getDatabaseConnection(): ?string
    {
        return $this->data['database_connection'] ?? null;
    }

    public function getCacheDriver(): ?string
    {
        return $this->data['cache_driver'] ?? null;
    }

    public function getQueueConnection(): ?string
    {
        return $this->data['queue_connection'] ?? null;
    }

    public function getMailDriver(): ?string
    {
        return $this->data['mail_driver'] ?? null;
    }

    public function getStorageDisk(): ?string
    {
        return $this->data['storage_disk'] ?? null;
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
