<?php

namespace ghoststreet\craftaisearch\services;

use Craft;
use ghoststreet\craftaisearch\AiSearch;
use ghoststreet\craftaisearch\exceptions\RateLimitException;
use yii\base\Component;

/**
 * Counter atomicity is best-effort under the default file/db cache; deploy
 * with Redis/Memcached for stricter guarantees under high concurrency.
 */
class RateLimitService extends Component
{
    public const KIND_SEARCH = 'search';
    public const KIND_RAG = 'rag';

    private const WINDOW_MINUTE = 60;
    private const WINDOW_HOUR = 3600;
    private const WINDOW_DAY = 86400;

    private const CONCURRENCY_TTL = 120;

    /** @throws RateLimitException */
    public function acquire(string $kind, string $ip): string
    {
        $settings = AiSearch::getInstance()->getSettings();

        [$perMin, $perHour] = match ($kind) {
            self::KIND_RAG => [$settings->rateLimitRagPerMinute, $settings->rateLimitRagPerHour],
            default => [$settings->rateLimitSearchPerMinute, $settings->rateLimitSearchPerHour],
        };

        $this->enforceWindow("rate:{$kind}:m:{$ip}", $perMin, self::WINDOW_MINUTE);
        $this->enforceWindow("rate:{$kind}:h:{$ip}", $perHour, self::WINDOW_HOUR);

        if ($kind === self::KIND_RAG) {
            $this->enforceBudget("cost:daily:ip:{$ip}", $settings->costBudgetDailyPerIp, 'per-IP');
            $this->enforceBudget('cost:daily:global', $settings->costBudgetDailyGlobal, 'global');

            $this->incrementConcurrency("conc:rag:ip:{$ip}", $settings->ragConcurrencyPerIp, 'per-IP');
            try {
                $this->incrementConcurrency('conc:rag:global', $settings->ragConcurrencyGlobal, 'global');
            } catch (RateLimitException $e) {
                $this->decrementConcurrency("conc:rag:ip:{$ip}");
                throw $e;
            }

            return 'rag:' . $ip;
        }

        return '';
    }

    public function release(string $token): void
    {
        if ($token === '' || !str_starts_with($token, 'rag:')) {
            return;
        }

        $ip = substr($token, 4);
        $this->decrementConcurrency("conc:rag:ip:{$ip}");
        $this->decrementConcurrency('conc:rag:global');
    }

    public function recordCost(string $ip, float $costUsd): void
    {
        if ($costUsd <= 0) {
            return;
        }

        $this->addToBudget("cost:daily:ip:{$ip}", $costUsd);
        $this->addToBudget('cost:daily:global', $costUsd);
    }

    private function enforceWindow(string $key, int $max, int $ttl): void
    {
        $cache = Craft::$app->getCache();
        $cache->add($key, 0, $ttl);
        $current = (int)$cache->get($key);

        if ($current >= $max) {
            throw RateLimitException::tooManyRequests($ttl);
        }

        $cache->set($key, $current + 1, $ttl);
    }

    private function incrementConcurrency(string $key, int $max, string $scope): void
    {
        $cache = Craft::$app->getCache();
        $cache->add($key, 0, self::CONCURRENCY_TTL);
        $current = (int)$cache->get($key);

        if ($current >= $max) {
            throw RateLimitException::concurrencyExceeded($scope);
        }

        $cache->set($key, $current + 1, self::CONCURRENCY_TTL);
    }

    private function decrementConcurrency(string $key): void
    {
        $cache = Craft::$app->getCache();
        $current = (int)$cache->get($key);

        if ($current <= 0) {
            return;
        }

        $cache->set($key, $current - 1, self::CONCURRENCY_TTL);
    }

    private function enforceBudget(string $key, float $cap, string $scope): void
    {
        if ($cap <= 0) {
            return;
        }

        $cache = Craft::$app->getCache();
        $spent = (float)$cache->get($key);

        if ($spent >= $cap) {
            throw RateLimitException::budgetExhausted($scope, self::WINDOW_DAY);
        }
    }

    private function addToBudget(string $key, float $costUsd): void
    {
        $cache = Craft::$app->getCache();
        $cache->add($key, 0.0, self::WINDOW_DAY);
        $current = (float)$cache->get($key);
        $cache->set($key, $current + $costUsd, self::WINDOW_DAY);
    }
}
