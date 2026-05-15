<?php

namespace ghoststreet\craftaisearch\helpers;

use craft\web\Controller;
use ghoststreet\craftaisearch\exceptions\AiSearchException;
use ghoststreet\craftaisearch\exceptions\ErrorCode;
use ghoststreet\craftaisearch\exceptions\RateLimitException;
use Throwable;
use yii\web\Response;

/**
 * Helper for creating standardized API responses.
 *
 * Strict error shape: { success: false, code, requestId?, retryAfter? }.
 * Stack traces never appear in API responses — they go to ai-search.log.
 */
final class ApiResponseHelper
{
    public const MAX_LIMIT = 100;
    public const MAX_QUERY_LENGTH = 150;

    /**
     * Build a strict error body. Always logs the exception with full trace.
     *
     * @return array{success: false, code: string, requestId?: string, retryAfter?: int}
     */
    public static function error(Throwable $e, string $operation = 'API error', array $context = []): array
    {
        $code = ErrorMapper::codeFor($e);
        Logger::exception($e, $operation, $context + ['code' => $code->value]);

        $body = ['success' => false, 'code' => $code->value];

        if (!empty($context['requestId'])) {
            $body['requestId'] = $context['requestId'];
        }

        if ($e instanceof RateLimitException) {
            $body['retryAfter'] = $e->retryAfterSeconds;
        }

        return $body;
    }

    /**
     * Build a JSON error Response with the correct status code (and Retry-After if applicable).
     */
    public static function jsonError(Controller $controller, Throwable $e, string $operation = 'API error', array $context = []): Response
    {
        $status = $e instanceof AiSearchException ? $e->httpStatus() : 500;
        $response = $controller->asJson(self::error($e, $operation, $context))->setStatusCode($status);

        if ($e instanceof RateLimitException) {
            $response->getHeaders()->set('Retry-After', (string) $e->retryAfterSeconds);
        }

        return $response;
    }

    /**
     * Validate query parameter; return strict error body if invalid, null if valid.
     *
     * @return array{success: false, code: string}|null
     */
    public static function validateQuery(string $query): ?array
    {
        if (TextValidator::isEmpty($query) || mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            return ['success' => false, 'code' => ErrorCode::SEARCH_VALIDATION_FAILED->value];
        }

        return null;
    }

    public static function validateLimit(int $limit, int $default = 10): int
    {
        if ($limit < 1) {
            return $default;
        }

        return min($limit, self::MAX_LIMIT);
    }
}
