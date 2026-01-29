<?php

namespace ghoststreet\craftaisearch\console\controllers;

use craft\console\Controller;
use craft\elements\Entry;
use ghoststreet\craftaisearch\AiSearch;
use ghoststreet\craftaisearch\exceptions\AiSearchException;
use ghoststreet\craftaisearch\exceptions\DatabaseException;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Console controller for bulk indexing entries into the AI search vector database.
 *
 * Supports filtering by site and section. By default performs an incremental
 * reindex (upserts). Use --wipe to clear all vectors before re-indexing.
 */
class IndexController extends Controller
{
    private const BATCH_SIZE = 10;

    public $defaultAction = 'index';
    public ?int $siteId = null;
    public ?string $section = null;
    public bool $wipe = false;

    /**
     * Register CLI options for the index action.
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        switch ($actionID) {
            case 'index':
                $options[] = 'siteId';
                $options[] = 'section';
                $options[] = 'wipe';
                break;
        }

        return $options;
    }

    /**
     * Bulk-index all entries (or a filtered subset).
     * Incremental by default (upserts existing vectors). Use --wipe to clear first.
     */
    public function actionIndex(): int
    {
        $this->stdout("Starting bulk indexing...\n", Console::FG_GREEN);

        try {
            if (!AiSearch::getInstance()->databaseService->isSchemaInitialized()) {
                $this->stdout("Initializing database schema...\n");
                AiSearch::getInstance()->databaseService->initializeSchema();
            }

            if ($this->wipe) {
                $this->stdout("Wiping existing vectors...\n");
                $count = AiSearch::getInstance()->databaseService->clearAllVectors();
                $this->stdout("Deleted {$count} existing vectors.\n");
            } else {
                $this->stdout("Incremental mode: existing vectors will be updated.\n");
            }
        } catch (DatabaseException $e) {
            $this->stdout("Database error: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $query = Entry::find();

        if ($this->siteId !== null) {
            $query->siteId($this->siteId);
        }

        if ($this->section !== null) {
            $query->section($this->section);
        }

        $total = $query->count();

        $this->stdout("Found {$total} entries to index.\n");

        if ($total === 0) {
            $this->stdout("No entries to index.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $indexedCount = 0;
        $failedCount = 0;

        foreach ($query->batch(self::BATCH_SIZE) as $batch) {
            foreach ($batch as $entry) {
                try {
                    AiSearch::getInstance()->embeddingService->indexElement($entry);
                    $indexedCount++;
                    $this->stdout(".");
                } catch (AiSearchException $e) {
                    $failedCount++;
                    $this->stdout("F", Console::FG_RED);
                    $this->stdout(" Failed to index entry {$entry->id}: {$e->getMessage()}\n", Console::FG_RED);
                }
            }
        }

        $this->stdout("\n");
        $this->stdout("Indexing complete!\n", Console::FG_GREEN);
        $this->stdout("Indexed: {$indexedCount}\n");

        if ($failedCount > 0) {
            $this->stdout("Failed: {$failedCount}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }
}
