<?php

namespace App\DTOs;

/**
 * Generic API response wrapper
 */
class ApiResponse
{
    public function __construct(
        public readonly mixed $data,
        public readonly array $meta = [],
        public readonly bool $success = true,
        public readonly ?string $message = null
    ) {
    }

    public static function success(mixed $data, array $meta = [], ?string $message = null): self
    {
        return new self($data, $meta, true, $message);
    }

    public static function failure(?string $message = null, mixed $data = null, array $meta = []): self
    {
        return new self($data, $meta, false, $message);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }
}
