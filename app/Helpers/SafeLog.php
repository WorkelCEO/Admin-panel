<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Safe logging helper to prevent recursive logging failures
 */
class SafeLog
{
    private static bool $loggingFailed = false;

    /**
     * Safely log an info message
     */
    public static function info(string $message, array $context = []): void
    {
        self::safeLog('info', $message, $context);
    }

    /**
     * Safely log an error message
     */
    public static function error(string $message, array $context = []): void
    {
        self::safeLog('error', $message, $context);
    }

    /**
     * Safely log a warning message
     */
    public static function warning(string $message, array $context = []): void
    {
        self::safeLog('warning', $message, $context);
    }

    /**
     * Safely log a debug message
     */
    public static function debug(string $message, array $context = []): void
    {
        self::safeLog('debug', $message, $context);
    }

    /**
     * Safely execute a log operation with error handling
     */
    private static function safeLog(string $level, string $message, array $context = []): void
    {
        // If logging has already failed, don't attempt to log again to prevent recursion
        if (self::$loggingFailed) {
            return;
        }

        try {
            // Remove any large context data that might cause issues
            $safeContext = self::sanitizeContext($context);
            
            Log::{$level}($message, $safeContext);
        } catch (Exception $e) {
            // Mark that logging has failed to prevent recursion
            self::$loggingFailed = true;
            
            // Only output to error_log to avoid recursive failures
            // Don't try to log this exception
            error_log("Logging failed: {$e->getMessage()}");
        }
    }

    /**
     * Sanitize context data to prevent overly large log entries
     */
    private static function sanitizeContext(array $context): array
    {
        $sanitized = [];
        $maxSize = 10000; // Maximum size per context value in bytes

        foreach ($context as $key => $value) {
            if (is_string($value) && strlen($value) > $maxSize) {
                $sanitized[$key] = substr($value, 0, $maxSize) . '...[truncated]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeContext($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Reset the logging failed flag (useful for testing or recovery)
     */
    public static function reset(): void
    {
        self::$loggingFailed = false;
    }
}
