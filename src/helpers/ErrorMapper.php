<?php

namespace ghoststreet\craftaisearch\helpers;

use Craft;
use ghoststreet\craftaisearch\exceptions\AiSearchException;
use ghoststreet\craftaisearch\exceptions\ErrorCode;
use Throwable;

/**
 * Maps any Throwable to a stable ErrorCode. API responses serialize the code
 * value only; server-rendered surfaces (CP flashes, Twig views) can use
 * translatedMessage() to localize via Craft::t('ai-search', ...).
 */
final class ErrorMapper
{
    public static function codeFor(Throwable $e): ErrorCode
    {
        return $e instanceof AiSearchException ? $e->errorCode() : ErrorCode::UNKNOWN;
    }

    public static function translatedMessage(Throwable $e): string
    {
        return Craft::t('ai-search', self::codeFor($e)->message());
    }

    /**
     * Log the exception and return a translated message. For non-JSON surfaces
     * (CP flashes, Twig views) that want both side-effects in one call.
     */
    public static function present(Throwable $e, string $operation, array $context = []): string
    {
        $code = self::codeFor($e);
        Logger::exception($e, $operation, $context + ['code' => $code->value]);
        return Craft::t('ai-search', $code->message());
    }
}
