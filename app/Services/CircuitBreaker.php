<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Circuit Breaker implementation for API resilience
 * 
 * States:
 * - CLOSED: Normal operation, requests pass through
 * - OPEN: Circuit is open, requests fail fast
 * - HALF_OPEN: Testing if service has recovered
 */
class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private const DEFAULT_FAILURE_THRESHOLD = 5;
    private const DEFAULT_TIMEOUT = 60; // seconds
    private const DEFAULT_SUCCESS_THRESHOLD = 2; // successes needed to close from half-open

    public function __construct(
        private string $serviceName,
        private int $failureThreshold = self::DEFAULT_FAILURE_THRESHOLD,
        private int $timeout = self::DEFAULT_TIMEOUT,
        private int $successThreshold = self::DEFAULT_SUCCESS_THRESHOLD
    ) {
    }

    /**
     * Check if circuit allows the request
     */
    public function allowsRequest(): bool
    {
        $state = $this->getState();

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            // Check if timeout has passed
            $openedAt = Cache::get($this->getOpenedAtKey());
            if ($openedAt && (time() - $openedAt) >= $this->timeout) {
                // Transition to half-open
                $this->setState(self::STATE_HALF_OPEN);
                return true;
            }
            return false;
        }

        // HALF_OPEN - allow request to test recovery
        return true;
    }

    /**
     * Record a successful request
     */
    public function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $successCount = Cache::increment($this->getHalfOpenSuccessKey());
            if ($successCount >= $this->successThreshold) {
                // Close the circuit
                $this->reset();
            }
        } else {
            // Reset failure count on success
            Cache::forget($this->getFailureCountKey());
        }
    }

    /**
     * Record a failed request
     */
    public function recordFailure(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            // Failed during half-open, reopen circuit
            $this->setState(self::STATE_OPEN);
            Cache::put($this->getOpenedAtKey(), time(), $this->timeout * 2);
        } else {
            $failureCount = Cache::increment($this->getFailureCountKey());
            
            if ($failureCount >= $this->failureThreshold) {
                // Open the circuit
                $this->setState(self::STATE_OPEN);
                Cache::put($this->getOpenedAtKey(), time(), $this->timeout * 2);
            }
        }
    }

    /**
     * Get current circuit state
     */
    public function getState(): string
    {
        return Cache::get($this->getStateKey(), self::STATE_CLOSED);
    }

    /**
     * Check if circuit is open
     */
    public function isOpen(): bool
    {
        return $this->getState() === self::STATE_OPEN;
    }

    /**
     * Reset circuit to closed state
     */
    public function reset(): void
    {
        Cache::forget($this->getStateKey());
        Cache::forget($this->getFailureCountKey());
        Cache::forget($this->getOpenedAtKey());
        Cache::forget($this->getHalfOpenSuccessKey());
    }

    /**
     * Set circuit state
     */
    private function setState(string $state): void
    {
        Cache::put($this->getStateKey(), $state, $this->timeout * 2);
    }

    private function getStateKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:state";
    }

    private function getFailureCountKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:failures";
    }

    private function getOpenedAtKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:opened_at";
    }

    private function getHalfOpenSuccessKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:half_open_success";
    }
}
