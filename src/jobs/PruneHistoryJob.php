<?php

namespace ghoststreet\craftsmartsearch\jobs;

use craft\queue\BaseJob;
use ghoststreet\craftsmartsearch\SmartSearch;

/**
 * Job to prune old search-history detail rows past the configured retention window.
 * Stats rows are never touched.
 */
class PruneHistoryJob extends BaseJob
{
    public int $retentionDays = 30;

    public function execute($queue): void
    {
        SmartSearch::getInstance()->historyService->pruneOlderThan($this->retentionDays);
    }

    protected function defaultDescription(): ?string
    {
        return "Pruning AI search history older than {$this->retentionDays} days";
    }
}
