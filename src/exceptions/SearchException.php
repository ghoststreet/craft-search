<?php

namespace ghoststreet\craftaisearch\exceptions;

use Throwable;

/**
 * Exception for search operation failures.
 * Thrown when semantic, hybrid, or RAG search operations fail.
 */
class SearchException extends AiSearchException
{
    public static function semanticSearchFailed(string $reason, Throwable $previous): self
    {
        return new self("Semantic search failed: {$reason}", 0, $previous);
    }

    public static function ragSearchFailed(string $reason, Throwable $previous): self
    {
        return new self("RAG search failed: {$reason}", 0, $previous);
    }

    public static function vectorQueryFailed(Throwable $previous): self
    {
        return new self(
            "Failed to perform vector similarity search: {$previous->getMessage()}",
            0,
            $previous
        );
    }

    public static function indexEntryNotFound(int $entryId, int $siteId): self
    {
        return new self("Entry #{$entryId} not found for site #{$siteId}");
    }
}
