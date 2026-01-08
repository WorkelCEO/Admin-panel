<?php

namespace App\Exceptions;

/**
 * Exception thrown when API connection fails
 */
class ApiConnectionException extends ApiException
{
    public function __construct(
        string $message = 'Failed to connect to API',
        ?string $endpoint = null,
        ?array $context = null,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, 0, $previous, $endpoint, $context);
    }
}
