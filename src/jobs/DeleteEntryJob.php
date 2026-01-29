<?php

namespace ghoststreet\craftaisearch\jobs;

use craft\queue\BaseJob;
use ghoststreet\craftaisearch\AiSearch;
use ghoststreet\craftaisearch\helpers\Logger;

/**
 * Job to delete vectors for a removed entry
 */
class DeleteEntryJob extends BaseJob
{
    public int $entryId;
    public int $siteId;

    public function execute($queue): void
    {
        AiSearch::getInstance()->embeddingService->deleteVector($this->entryId, $this->siteId);
        Logger::info('Deleted vectors via job', ['entryId' => $this->entryId, 'siteId' => $this->siteId]);
    }

    protected function defaultDescription(): ?string
    {
        return "Deleting AI search vectors for entry #{$this->entryId}";
    }
}
