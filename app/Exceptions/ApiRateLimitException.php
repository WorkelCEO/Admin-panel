<?php

namespace App\Exceptions;

/**
 * Exception thrown when API rate limit is exceeded
 */
class ApiRateLimitException extends ApiException
{
    protected ?int $retryAfter = null;

    public function __construct(
        string $message = 'API rate limit exceeded',
        ?string $endpoint = null,
        ?int $retryAfter = null,
        ?array $context = null,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, 429, $previous, $endpoint, $context);
        $this->retryAfter = $retryAfter;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
