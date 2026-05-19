<?php

namespace ghoststreet\craftsmartsearch\jobs;

use craft\base\Batchable;
use craft\elements\Entry;

/**
 * Batchable yielding [entryId, siteId] pairs for every enabled entry with a
 * URI across all sites. Used by SyncSearchIndexJob to feed Craft's batched-job
 * pipeline (per-item progress, automatic continuation jobs).
 */
class EntrySiteList implements Batchable
{
    private ?int $cachedCount = null;

    public function __construct(private readonly ?int $siteId = null)
    {
    }

    public function count(): int
    {
        return $this->cachedCount ??= (int)$this->baseQuery()->count();
    }

    public function getSlice(int $offset, int $limit): iterable
    {
        return $this->baseQuery()
            ->offset($offset)
            ->limit($limit)
            ->orderBy(['elements.id' => SORT_ASC, 'elements_sites.siteId' => SORT_ASC])
            ->all();
    }

    private function baseQuery()
    {
        return Entry::find()
            ->siteId($this->siteId ?? '*')
            ->unique(false)
            ->status(Entry::STATUS_ENABLED)
            ->uri(':notempty:')
            ->select(['elements.id', 'elements_sites.siteId'])
            ->asArray();
    }
}
