<?php

namespace ghoststreet\craftaisearch\jobs;

use craft\queue\BaseJob;
use ghoststreet\craftaisearch\AiSearch;
use ghoststreet\craftaisearch\helpers\Logger;

/**
 * Queue job to remove vectors for entries that are no longer valid.
 *
 * Pushed as the last job after all IndexEntryJob jobs during a sync,
 * so stale vectors are cleaned up only after re-indexing completes.
 */
class CleanupStaleVectorsJob extends BaseJob
{
    /** @var array<array{elementId: int, siteId: int}> */
    public array $validPairs = [];

    public function execute($queue): void
    {
        $count = AiSearch::getInstance()->databaseService->deleteVectorsNotInPairs($this->validPairs);
        Logger::info('Cleaned up stale vectors', ['deleted' => $count]);
    }

    protected function defaultDescription(): ?string
    {
        return 'Cleaning up stale AI search vectors';
    }
}
