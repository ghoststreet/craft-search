<?php

namespace ghoststreet\craftsmartsearch\console\controllers;

use ghoststreet\craftsmartsearch\SmartSearch;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Console commands for Smart Search history.
 *
 * Usage:
 *   php craft smart-search/history/prune
 *   php craft smart-search/history/clear
 */
class HistoryController extends Controller
{
    public function actionPrune(?int $days = null): int
    {
        $days = $days ?? SmartSearch::getInstance()->getSettings()->historyRetentionDays;
        $deleted = SmartSearch::getInstance()->historyService->pruneOlderThan($days);
        $this->stdout("Pruned {$deleted} history detail rows older than {$days} days.\n");
        return ExitCode::OK;
    }

    public function actionClear(): int
    {
        $deleted = SmartSearch::getInstance()->historyService->clearAllDetails();
        $this->stdout("Cleared {$deleted} history detail rows. Stats preserved.\n");
        return ExitCode::OK;
    }
}
