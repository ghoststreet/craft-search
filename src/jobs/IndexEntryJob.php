<?php

namespace ghoststreet\craftaisearch\jobs;

use craft\elements\Entry;
use craft\queue\BaseJob;
use ghoststreet\craftaisearch\AiSearch;
use ghoststreet\craftaisearch\helpers\Logger;
use RuntimeException;

/**
 * Queue job to index a single entry for AI search.
 *
 * Fetches the entry by ID and site, extracts text, generates embeddings,
 * and stores vectors. Throws if the entry no longer exists so the job
 * queue marks the job as failed rather than silently succeeding.
 */
class IndexEntryJob extends BaseJob
{
    public int $entryId;
    public int $siteId;

    /**
     * @throws RuntimeException If the entry no longer exists
     */
    public function execute($queue): void
    {
        $entry = Entry::find()
            ->id($this->entryId)
            ->siteId($this->siteId)
            ->status(null)
            ->one();

        if (!$entry) {
            throw new RuntimeException("Entry #{$this->entryId} not found for site #{$this->siteId}");
        }

        AiSearch::getInstance()->embeddingService->indexElement($entry);
        Logger::info('Indexed entry via job', ['entryId' => $this->entryId]);
    }

    protected function defaultDescription(): ?string
    {
        return "Indexing entry #{$this->entryId} for AI search";
    }
}
