<?php

namespace App\Services\Http;

use App\Exceptions\ApiException;
use App\Exceptions\ApiTimeoutException;
use Illuminate\Http\Client\RequestException;

/**
 * Retry Handler - Handles retry logic for API requests
 */
class RetryHandler
{
    private int $maxRetries;
    private array $retryDelays;
    private int $timeout;
    private int $retryTimeout;

    public function __construct(
        int $maxRetries = 3,
        array $retryDelays = [1, 2, 4],
        int $timeout = 10,
        int $retryTimeout = 30
    ) {
        $this->maxRetries = $maxRetries;
        $this->retryDelays = $retryDelays;
        $this->timeout = $timeout;
        $this->retryTimeout = $retryTimeout;
    }

    /**
     * Execute request with retry logic
     * 
     * @param callable $requestCallback Function that makes the HTTP request
     * @param string $endpoint Endpoint name for logging
     * @return mixed Response from the request
     * @throws ApiException
     */
    public function execute(callable $requestCallback, string $endpoint): mixed
    {
        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                // Apply delay for retries (not first attempt)
                if ($attempt > 0) {
                    $delay = $this->retryDelays[$attempt - 1] ?? end($this->retryDelays);
                    sleep($delay);
                }

                // Execute request with appropriate timeout
                $timeout = $attempt > 0 ? $this->retryTimeout : $this->timeout;
                
                return $requestCallback($timeout);

            } catch (ApiException $e) {
                // Don't retry API exceptions (401, 403, 404, 422, etc.)
                throw $e;
            } catch (RequestException $e) {
                $lastException = $e;

                // Check if it's a timeout
                if ($this->isTimeout($e)) {
                    if ($attempt === $this->maxRetries) {
                        throw new ApiTimeoutException(
                            "Request timed out after {$this->maxRetries} attempts",
                            $endpoint,
                            ['attempts' => $attempt + 1],
                            $e
                        );
                    }
                    continue; // Retry timeout errors
                }

                // Connection errors - retry if not last attempt
                if ($attempt === $this->maxRetries) {
                    throw new \App\Exceptions\ApiConnectionException(
                        "Failed to connect to API after {$this->maxRetries} attempts: " . $e->getMessage(),
                        $endpoint,
                        ['attempts' => $attempt + 1],
                        $e
                    );
                }
            } catch (\Exception $e) {
                $lastException = $e;

                // Don't retry unexpected exceptions on last attempt
                if ($attempt === $this->maxRetries) {
                    throw new ApiException(
                        "Unexpected error: " . $e->getMessage(),
                        0,
                        $e,
                        $endpoint
                    );
                }
            }
        }

        // Should never reach here, but handle it just in case
        throw new ApiException(
            "Request failed after all retry attempts",
            0,
            $lastException,
            $endpoint
        );
    }

    /**
     * Check if exception is a timeout
     */
    private function isTimeout(RequestException $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'timeout') || str_contains($message, 'timed out');
    }
}
