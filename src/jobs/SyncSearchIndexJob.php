<?php

namespace ghoststreet\craftaisearch\jobs;

use Craft;
use craft\base\Batchable;
use craft\elements\Entry;
use craft\i18n\Translation;
use craft\queue\BaseBatchedJob;
use ghoststreet\craftaisearch\AiSearch;
use ghoststreet\craftaisearch\helpers\Logger;

/**
 * Walks every enabled entry that has a URI and re-indexes those whose extracted
 * text hash has changed since the last sync. Unchanged entries are skipped
 * inside EmbeddingService::indexElement, so the per-item cost is one DB read
 * for the stored hash.
 *
 * Uses Craft's BaseBatchedJob so the CP queue UI shows native progress and an
 * "X of Y" label, and continuation jobs are spawned automatically to respect
 * TTR and memory limits.
 */
class SyncSearchIndexJob extends BaseBatchedJob
{
    protected function loadData(): Batchable
    {
        return new EntrySiteList();
    }

    protected function processItem(mixed $item): void
    {
        $entryId = (int)$item['id'];
        $siteId = (int)$item['siteId'];

        $entry = Entry::find()
            ->id($entryId)
            ->siteId($siteId)
            ->status(null)
            ->one();

        if (!$entry) {
            Logger::debug('Sync skipped: entry vanished mid-run', ['entryId' => $entryId, 'siteId' => $siteId]);
            return;
        }

        if ($entry->getStatus() === Entry::STATUS_DISABLED) {
            AiSearch::getInstance()->embeddingService->deleteVector($entryId, $siteId);
            return;
        }

        AiSearch::getInstance()->embeddingService->indexElement($entry);
    }

    protected function defaultDescription(): ?string
    {
        return Translation::prep('ai-search', 'Syncing AI search index');
    }
}
