<?php

namespace App\DTOs;

use App\Models\EmailLog;

/**
 * Paginated email log list response
 */
class EmailLogListResponse extends ApiResponse
{
    /**
     * @param EmailLog[] $data
     */
    public function __construct(
        array $data,
        array $meta = [],
        bool $success = true,
        ?string $message = null
    ) {
        parent::__construct($data, $meta, $success, $message);
    }

    public function getEmailLogs(): array
    {
        return $this->data ?? [];
    }

    public function getTotal(): int
    {
        return $this->meta['total'] ?? 0;
    }

    public function getPerPage(): int
    {
        return $this->meta['per_page'] ?? 15;
    }

    public function getCurrentPage(): int
    {
        return $this->meta['current_page'] ?? 1;
    }

    public function getLastPage(): int
    {
        return $this->meta['last_page'] ?? 1;
    }

    public static function fromApiResponse(array $response): self
    {
        $emailLogs = collect($response['data'] ?? [])
            ->map(fn($item) => EmailLog::fromApi($item))
            ->all();

        return new self(
            $emailLogs,
            $response['meta'] ?? [],
            true
        );
    }
}
