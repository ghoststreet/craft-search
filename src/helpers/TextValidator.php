<?php

namespace ghoststreet\craftaisearch\helpers;

/**
 * Helper for text validation operations.
 *
 * Consolidates the repeated empty(trim($text)) pattern across the codebase
 * into reusable methods with consistent behavior.
 */
final class TextValidator
{
    /**
     * Check if a string is empty or contains only whitespace.
     *
     * @param string|null $text The text to check
     * @return bool True if the text is null, empty, or whitespace-only
     */
    public static function isEmpty(?string $text): bool
    {
        return $text === null || trim($text) === '';
    }

    /**
     * Check if a string has meaningful content (not empty or whitespace-only).
     *
     * @param string|null $text The text to check
     * @return bool True if the text contains non-whitespace characters
     */
    public static function isNotEmpty(?string $text): bool
    {
        return !self::isEmpty($text);
    }
}
