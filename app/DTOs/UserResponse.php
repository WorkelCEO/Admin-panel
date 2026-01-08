<?php

namespace App\DTOs;

/**
 * User data DTO
 */
class UserResponse extends ApiResponse
{
    public function getId(): ?string
    {
        return $this->data['id'] ?? null;
    }

    public function getName(): ?string
    {
        return $this->data['name'] ?? null;
    }

    public function getEmail(): ?string
    {
        return $this->data['email'] ?? null;
    }

    public function getSystemRole(): ?string
    {
        return $this->data['system_role'] ?? null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->data['is_super_admin'] ?? false;
    }

    public function getEmailVerifiedAt(): ?string
    {
        return $this->data['email_verified_at'] ?? null;
    }

    public function getLastSeen(): ?string
    {
        return $this->data['last_seen'] ?? null;
    }

    public function getWorkspacesCount(): int
    {
        return $this->data['workspaces_count'] ?? 0;
    }

    public function getProjectsCount(): int
    {
        return $this->data['projects_count'] ?? 0;
    }

    public function getTasksCount(): int
    {
        return $this->data['tasks_count'] ?? 0;
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
