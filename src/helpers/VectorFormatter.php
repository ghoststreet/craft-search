<?php

namespace ghoststreet\craftaisearch\helpers;

/**
 * Helper for formatting vectors for PostgreSQL pgvector operations.
 *
 * Consolidates the repeated vector string formatting pattern used
 * when storing and querying embeddings in the database.
 */
final class VectorFormatter
{
    /**
     * Format a vector array as a PostgreSQL pgvector string.
     *
     * @param array<float> $vector The embedding vector to format
     * @return string The vector formatted as '[0.1,0.2,0.3,...]'
     */
    public static function toPgVector(array $vector): string
    {
        return '[' . implode(',', $vector) . ']';
    }
}
