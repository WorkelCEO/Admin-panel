<?php

namespace App\DTOs;

/**
 * Backup data DTO
 */
class BackupResponse extends ApiResponse
{
    public function getId(): ?string
    {
        return $this->data['id'] ?? null;
    }

    public function getFilename(): ?string
    {
        return $this->data['filename'] ?? null;
    }

    public function getSize(): ?string
    {
        return $this->data['size'] ?? null;
    }

    public function getCreatedAt(): ?string
    {
        return $this->data['created_at'] ?? null;
    }

    public function getStatus(): ?string
    {
        return $this->data['status'] ?? null;
    }

    public function getDescription(): ?string
    {
        return $this->data['description'] ?? null;
    }

    public static function fromApiResponse(array $response): self
    {
        return new self(
            $response['data'] ?? $response,
            [],
            $response['success'] ?? true,
            $response['message'] ?? null
        );
    }
}
