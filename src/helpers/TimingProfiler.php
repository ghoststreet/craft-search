<?php

namespace ghoststreet\craftaisearch\helpers;

/**
 * Helper for profiling and timing operations.
 *
 * Consolidates duplicate timing patterns across the codebase into a single,
 * reusable method that uses the Logger for consistent output.
 */
final class TimingProfiler
{
    /**
     * Execute a callable and log its execution time.
     *
     * @param string $operation Human-readable name of the operation being timed
     * @param callable $callback The operation to execute and time
     * @param array $context Additional context to include in the log message
     * @return mixed The return value of the callback
     */
    public static function profile(string $operation, callable $callback, array $context = []): mixed
    {
        $start = microtime(true);
        $result = $callback();
        $durationMs = round((microtime(true) - $start) * 1000, 2);

        Logger::timing($operation, $durationMs, $context);

        return $result;
    }
}
