<?php

namespace ghoststreet\craftaisearch\helpers;

/**
 * Helper for content pattern matching.
 * Centralizes regex patterns used for text processing.
 */
final class ContentPatterns
{
    /** Pattern for paragraph breaks (double newlines) */
    public const PARAGRAPH_BREAK = '/\n\s*\n/';

    /** Pattern for sentence boundaries */
    public const SENTENCE_BOUNDARY = '/(?<=[.!?])\s+/';

    /**
     * Split text into paragraphs using double-newline boundaries.
     *
     * @return string[] Non-empty paragraph strings
     */
    public static function splitParagraphs(string $text): array
    {
        return preg_split(self::PARAGRAPH_BREAK, $text, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Split text into sentences at punctuation boundaries (.!?).
     *
     * @return string[] Non-empty sentence strings
     */
    public static function splitSentences(string $text): array
    {
        return preg_split(self::SENTENCE_BOUNDARY, $text, -1, PREG_SPLIT_NO_EMPTY);
    }
}
