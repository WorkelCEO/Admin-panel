<?php

namespace App\DTOs;

/**
 * System statistics DTO
 */
class SystemStatsResponse extends ApiResponse
{
    public function getUsersCount(): int
    {
        return $this->data['users_count'] ?? 0;
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

    public function getActiveSessions(): int
    {
        return $this->data['active_sessions'] ?? 0;
    }

    public function getStorageUsage(): ?array
    {
        return $this->data['storage_usage'] ?? null;
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
