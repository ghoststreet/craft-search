<?php

namespace ghoststreet\craftaisearch\helpers;

use Craft;
use Throwable;

/**
 * Helper for creating standardized API responses.
 *
 * Consolidates duplicate error response patterns across controller endpoints
 * into reusable methods with consistent formatting.
 */
final class ApiResponseHelper
{
    /** Maximum allowed limit for search results */
    public const MAX_LIMIT = 100;
    /** Maximum allowed query length in characters */
    public const MAX_QUERY_LENGTH = 1000;

    /**
     * Create an error response array.
     *
     * @param Throwable $e The exception that occurred
     * @return array{success: false, error: string, trace?: string}
     */
    public static function error(Throwable $e): array
    {
        Logger::exception($e, 'API error');

        $response = [
            'success' => false,
            'error' => $e->getMessage(),
        ];

        if (Craft::$app->getConfig()->getGeneral()->devMode) {
            $response['trace'] = $e->getTraceAsString();
        }

        return $response;
    }

    /**
     * Check if query parameter is valid and return validation error if not.
     *
     * @param string $query The query string to validate
     * @return array|null Returns validation error array if invalid, null if valid
     */
    public static function validateQuery(string $query): ?array
    {
        if (TextValidator::isEmpty($query)) {
            return ['success' => false, 'error' => 'Query parameter "q" is required'];
        }

        if (strlen($query) > self::MAX_QUERY_LENGTH) {
            return ['success' => false, 'error' => sprintf('Query exceeds maximum length of %d characters', self::MAX_QUERY_LENGTH)];
        }

        return null;
    }

    /**
     * Validate and constrain limit parameter to safe bounds.
     *
     * @param int $limit The requested limit
     * @param int $default Default limit if input is invalid
     * @return int Constrained limit between 1 and MAX_LIMIT
     */
    public static function validateLimit(int $limit, int $default = 10): int
    {
        if ($limit < 1) {
            return $default;
        }

        return min($limit, self::MAX_LIMIT);
    }
}
