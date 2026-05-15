<?php

namespace ghoststreet\craftaisearch\helpers;

use Craft;
use ghoststreet\craftaisearch\exceptions\AiSearchException;
use Throwable;

/**
 * Bridge between caught exceptions and what a user actually sees.
 *
 * Every controller catch that surfaces an error to the CP, an API caller, or
 * a template should route through here. Guarantees the exception is logged
 * and that no raw driver/SDK message reaches the user — typed exceptions
 * provide their own `userMessage()`; everything else gets a generic line
 * that points at the log.
 */
final class ErrorPresenter
{
    public static function present(Throwable $e, string $operation, array $context = []): string
    {
        Logger::exception($e, $operation, $context);

        if ($e instanceof AiSearchException) {
            return $e->userMessage();
        }

        return Craft::t(
            'ai-search',
            'Something went wrong while {operation}. The administrator can find the technical details in the AI Search log.',
            ['operation' => $operation]
        );
    }
}
