<?php

namespace ghoststreet\craftaisearch\controllers;

use Craft;
use craft\web\Controller;
use ghoststreet\craftaisearch\AiSearch;
use yii\web\Response;

/**
 * Aggregator for the AI Search dashboard. Pulls daily series, index coverage,
 * budget consumption, top queries, recent errors, and recommendations. Heavy
 * lookups (coverage by site) are cached for 60s to keep CP load snappy.
 */
class DashboardController extends Controller
{
    private const CACHE_TTL = 60;

    public function actionIndex(): Response
    {
        $this->requireAdmin();

        $plugin = AiSearch::getInstance();
        $settings = $plugin->getSettings();
        $stats = $plugin->databaseService->getStatsSafe();

        $history = $plugin->historyService;
        $cache = Craft::$app->getCache();

        $dailySeries = $history->getDailySeries(30);
        $cacheHitRate = $history->getCacheHitRate(30);
        $zeroResultRate = $history->getZeroResultRate(30);
        $topQueries = $history->getTopKeywords(7, null, 5);
        $zeroResults = $history->getZeroResultQueries(7, null, 3);
        $recentErrors = $history->getRecentErrors(5);

        $coverage = $cache->getOrSet(
            'aisearch_dash_coverage',
            fn() => $plugin->indexingDebugService->getCoverageBySite(),
            self::CACHE_TTL
        );

        $sevenDay = array_slice($dailySeries, -7);
        $sevenDayBurn = count($sevenDay) > 0
            ? array_sum(array_column($sevenDay, 'cost')) / count($sevenDay)
            : 0.0;
        $budget = $plugin->rateLimitService->getBudgetConsumption($sevenDayBurn);

        $aggregates = $this->computeAggregates($dailySeries);

        $recommendations = $plugin->recommendationsService->build([
            'dailySeries' => $dailySeries,
            'coverage' => $coverage,
            'budget' => $budget,
            'cacheHitRate' => $cacheHitRate,
            'zeroResultRate' => $zeroResultRate,
            'totalEntries' => (int)($stats['entryCount'] ?? 0),
        ]);

        $health = $this->computeHealth([
            'apiKey' => !empty($settings->getOpenaiApiKey()),
            'dbConnected' => (bool)($stats['isConnected'] ?? false),
            'lastIndexed' => $stats['lastIndexed'] ?? null,
            'budgetRatio' => $budget['ratio'],
            'errorRate' => $aggregates['errorRate30'],
            'coverage' => $coverage,
        ]);

        $setupComplete = !empty($settings->getOpenaiApiKey())
            && (bool)($stats['isConnected'] ?? false)
            && (int)($stats['entryCount'] ?? 0) > 0;

        return $this->renderTemplate('ai-search/index', [
            'plugin' => $plugin,
            'settings' => $settings,
            'stats' => $stats,
            'dailySeries' => $dailySeries,
            'aggregates' => $aggregates,
            'coverage' => $coverage,
            'budget' => $budget,
            'cacheHitRate' => $cacheHitRate,
            'zeroResultRate' => $zeroResultRate,
            'topQueries' => $topQueries,
            'zeroResults' => $zeroResults,
            'recentErrors' => $recentErrors,
            'recommendations' => $recommendations,
            'health' => $health,
            'setupComplete' => $setupComplete,
            'selectedSubnavItem' => 'dashboard',
        ]);
    }

    /**
     * Roll up the daily series into headline KPI numbers and prior-period deltas.
     */
    private function computeAggregates(array $series): array
    {
        $recent = array_slice($series, -15);
        $prior = array_slice($series, 0, max(0, count($series) - 15));

        $sum = static fn(array $rows, string $key) => array_sum(array_column($rows, $key));
        $count = static fn(array $rows, string $key) => array_sum(array_column($rows, $key));

        $recentSearches = $sum($recent, 'searches');
        $priorSearches = $sum($prior, 'searches');
        $recentCost = $sum($recent, 'cost');
        $priorCost = $sum($prior, 'cost');

        $allDurations = [];
        foreach ($series as $r) {
            if ($r['searches'] > 0 && $r['avgMs'] > 0) {
                $allDurations[] = ['avg' => $r['avgMs'], 'n' => $r['searches']];
            }
        }
        $avgDuration = 0;
        if ($allDurations) {
            $num = 0; $den = 0;
            foreach ($allDurations as $d) { $num += $d['avg'] * $d['n']; $den += $d['n']; }
            $avgDuration = $den > 0 ? (int)round($num / $den) : 0;
        }

        $errors30 = $count($series, 'errors');
        $searches30 = $count($series, 'searches');

        return [
            'searches30' => $searches30,
            'searchesDelta' => $this->pctDelta($recentSearches, $priorSearches),
            'cost30' => round($count($series, 'cost'), 4),
            'costDelta' => $this->pctDelta($recentCost, $priorCost),
            'avgDurationMs' => $avgDuration,
            'errors30' => $errors30,
            'errorRate30' => $searches30 > 0 ? round($errors30 / $searches30, 4) : 0.0,
        ];
    }

    private function pctDelta(float|int $recent, float|int $prior): ?float
    {
        if ($prior <= 0) {
            return $recent > 0 ? null : 0.0;
        }
        return round((($recent - $prior) / $prior) * 100, 1);
    }

    /**
     * Composite 0–100 health score with per-factor breakdown for tooltips.
     */
    private function computeHealth(array $f): array
    {
        $factors = [];

        $factors['apiKey'] = $f['apiKey'] ? 100 : 0;
        $factors['db'] = $f['dbConnected'] ? 100 : 0;

        $totalEntries = 0; $indexed = 0;
        foreach ($f['coverage'] as $c) {
            $totalEntries += (int)$c['total'];
            $indexed += (int)$c['indexed'];
        }
        $factors['coverage'] = $totalEntries > 0 ? (int)round(($indexed / $totalEntries) * 100) : 0;

        $factors['budget'] = (int)round((1 - min(1.0, $f['budgetRatio'])) * 100);
        $factors['errors'] = (int)round((1 - min(1.0, $f['errorRate'] * 10)) * 100);

        $score = (int)round(array_sum($factors) / count($factors));

        return [
            'score' => $score,
            'factors' => $factors,
            'level' => $score >= 80 ? 'good' : ($score >= 50 ? 'warn' : 'bad'),
        ];
    }
}
