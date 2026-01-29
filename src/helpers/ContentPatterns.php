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

    /** Pattern for extracting words (alphanumeric, min 2 chars) */
    public const WORD_EXTRACTION = '/[a-zA-Z0-9]{2,}/';

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

    /**
     * Extract unique lowercase words from text (alphanumeric sequences, min 2 chars).
     *
     * @return string[] Unique lowercase words
     */
    public static function extractWords(string $text): array
    {
        preg_match_all(self::WORD_EXTRACTION, strtolower($text), $matches);
        return array_unique($matches[0] ?? []);
    }
}
