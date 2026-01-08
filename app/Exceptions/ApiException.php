<?php

namespace App\Exceptions;

use Exception;

/**
 * Base exception for all API-related errors
 */
class ApiException extends Exception
{
    protected ?string $endpoint = null;
    protected ?array $context = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        ?string $endpoint = null,
        ?array $context = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->endpoint = $endpoint;
        $this->context = $context;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'endpoint' => $this->endpoint,
            'context' => $this->context,
        ];
    }
}
