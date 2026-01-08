<?php

namespace App\Exceptions;

/**
 * Exception thrown when API resource is not found (404)
 */
class ApiNotFoundException extends ApiException
{
    public function __construct(
        string $message = 'API resource not found',
        ?string $endpoint = null,
        ?array $context = null,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, 404, $previous, $endpoint, $context);
    }
}
