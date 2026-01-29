<?php

namespace ghoststreet\craftaisearch\variables;

use ghoststreet\craftaisearch\AiSearch;

/**
 * Twig variable class for AI Search.
 *
 * Provides `craft.aiSearch.search()` and `craft.aiSearch.rag()` for frontend templates.
 */
class AiSearchVariable
{
    /**
     * Perform a hybrid or semantic search from Twig templates.
     *
     * @param string $query The search query
     * @param int $limit Maximum number of results
     * @param int|null $siteId Optional site ID filter
     * @param bool $hybrid Whether to use hybrid search (default: true)
     * @return array Search results with element, score, and content
     */
    public function search(string $query, int $limit = 10, ?int $siteId = null, bool $hybrid = true): array
    {
        return AiSearch::getInstance()->searchService->search($query, $limit, $siteId, $hybrid);
    }

    /**
     * Perform a RAG search from Twig templates.
     *
     * @param string $query The search query
     * @param int $limit Maximum number of source entries
     * @param int|null $siteId Optional site ID filter
     * @return array RAG response with summary, sources, and confidence
     */
    public function rag(string $query, int $limit = 5, ?int $siteId = null): array
    {
        return AiSearch::getInstance()->ragSearchService->search($query, $limit, $siteId);
    }
}
