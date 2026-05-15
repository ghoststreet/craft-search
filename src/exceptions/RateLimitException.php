<?php

namespace ghoststreet\craftaisearch\exceptions;

class RateLimitException extends AiSearchException
{
    public int $retryAfterSeconds = 60;
    public string $reason = 'rate_limit';

    protected int $httpStatus = 429;

    public static function tooManyRequests(int $retryAfter): self
    {
        $e = new self('Too many requests. Slow down and retry shortly.');
        $e->retryAfterSeconds = $retryAfter;
        $e->reason = 'rate_limit';
        return $e;
    }

    public static function concurrencyExceeded(string $scope): self
    {
        $e = new self("Too many concurrent requests ({$scope} cap).");
        $e->retryAfterSeconds = 5;
        $e->reason = 'concurrency';
        return $e;
    }

    public static function budgetExhausted(string $scope, int $resetSeconds): self
    {
        $e = new self("Daily cost budget exhausted ({$scope}). Try again later.");
        $e->retryAfterSeconds = $resetSeconds;
        $e->reason = 'budget';
        $e->httpStatus = 503;
        return $e;
    }
}
