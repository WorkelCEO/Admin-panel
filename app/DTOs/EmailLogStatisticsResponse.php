<?php

namespace App\DTOs;

/**
 * Email log statistics response
 */
class EmailLogStatisticsResponse extends ApiResponse
{
    public function getTotal(): int
    {
        return $this->data['total'] ?? 0;
    }

    public function getSuccess(): int
    {
        return $this->data['success'] ?? 0;
    }

    public function getError(): int
    {
        return $this->data['error'] ?? 0;
    }

    public function getSuccessRate(): float
    {
        return $this->data['success_rate'] ?? 0.0;
    }

    public function getByDate(): array
    {
        return $this->data['by_date'] ?? [];
    }

    public static function fromApiResponse(array $response): self
    {
        return new self(
            $response['data'] ?? [],
            [],
            true
        );
    }
}
