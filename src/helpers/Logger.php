<?php

namespace ghoststreet\craftsmartsearch\helpers;

use Craft;
use Throwable;

/**
 * Centralized logging helper for the Smart Search plugin.
 *
 * All logs use the 'smart-search' category to ensure they go to
 * storage/logs/smart-search.log instead of being split across multiple log files.
 */
final class Logger
{
    private const CATEGORY = 'smart-search';

    /**
     * Log an informational message.
     * Use for successful operations worth noting (indexing complete, schema initialized, etc.)
     */
    public static function info(string $message, array $context = []): void
    {
        Craft::info(self::formatMessage($message, $context), self::CATEGORY);
    }

    /**
     * Log a warning message.
     * Use for recoverable issues or skipped items (entry has no URL, cache miss, etc.)
     */
    public static function warning(string $message, array $context = []): void
    {
        Craft::warning(self::formatMessage($message, $context), self::CATEGORY);
    }

    /**
     * Log an error message.
     * Use for failures that need attention (database errors, API failures, etc.)
     */
    public static function error(string $message, array $context = []): void
    {
        Craft::error(self::formatMessage($message, $context), self::CATEGORY);
    }

    /**
     * Log a debug message.
     * Only logs when devMode is enabled. Use for verbose tracing during development.
     */
    public static function debug(string $message, array $context = []): void
    {
        if (Craft::$app->getConfig()->getGeneral()->devMode) {
            Craft::info('[DEBUG] ' . self::formatMessage($message, $context), self::CATEGORY);
        }
    }

    /**
     * Log a timing/performance message.
     * Use for performance monitoring and profiling.
     */
    public static function timing(string $operation, float $durationMs, array $context = []): void
    {
        $contextSuffix = !empty($context) ? ', ' . self::formatMessage('', $context) : '';
        Craft::info("[TIMING] {$operation}: {$durationMs}ms{$contextSuffix}", self::CATEGORY);
    }

    /**
     * Log an exception with full context, including stack trace (always).
     */
    public static function exception(Throwable $e, string $operation, array $context = []): void
    {
        $context['exceptionMessage'] = $e->getMessage();
        $context['exceptionClass'] = get_class($e);
        $context['trace'] = $e->getTraceAsString();

        self::error("{$operation} failed: {$e->getMessage()}", $context);
    }

    /**
     * Format a message with context for consistent log output.
     */
    private static function formatMessage(string $message, array $context): string
    {
        if (empty($context)) {
            return $message;
        }

        $contextParts = [];
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $value = 'null';
            }
            $contextParts[] = "{$key}={$value}";
        }

        return $message . ' [' . implode(', ', $contextParts) . ']';
    }
}
