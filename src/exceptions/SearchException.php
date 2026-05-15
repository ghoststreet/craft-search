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
        $e = new self("Semantic search failed: {$reason}", 0, $previous);
        return $e->setUserMessage('Semantic search failed. The administrator can find the technical details in the AI Search log.');
    }

    public static function ragSearchFailed(string $reason, Throwable $previous): self
    {
        $e = new self("RAG search failed: {$reason}", 0, $previous);
        return $e->setUserMessage('RAG search failed. The administrator can find the technical details in the AI Search log.');
    }

    public static function vectorQueryFailed(Throwable $previous): self
    {
        $e = new self(
            "Failed to perform vector similarity search: {$previous->getMessage()}",
            0,
            $previous
        );
        return $e->setUserMessage('Vector similarity search failed. The administrator can find the technical details in the AI Search log.');
    }

    public static function indexEntryNotFound(int $entryId, int $siteId): self
    {
        $e = new self("Entry #{$entryId} not found for site #{$siteId}");
        $e->httpStatus = 404;
        return $e->setUserMessage('The requested entry could not be found.');
    }
}
