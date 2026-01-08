<?php

namespace App\Exceptions;

/**
 * Exception thrown when API request times out
 */
class ApiTimeoutException extends ApiException
{
    public function __construct(
        string $message = 'API request timed out',
        ?string $endpoint = null,
        ?array $context = null,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, 408, $previous, $endpoint, $context);
    }
}
