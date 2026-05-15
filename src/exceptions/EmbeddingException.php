<?php

namespace ghoststreet\craftaisearch\exceptions;

use Throwable;

/**
 * Exception for embedding generation failures.
 * Thrown when OpenAI API calls fail or return invalid data.
 */
class EmbeddingException extends AiSearchException
{
    public static function emptyText(): self
    {
        $e = new self('Cannot generate embedding: text cannot be empty');
        return $e->setUserMessage('Cannot generate an embedding for empty text.');
    }

    public static function rateLimited(Throwable $previous): self
    {
        $e = new self(
            'OpenAI API rate limit exceeded. Please wait a moment and try again.',
            0,
            $previous
        );
        $e->httpStatus = 429;
        return $e->setUserMessage('OpenAI rate limit reached. Please retry shortly.');
    }

    public static function quotaExceeded(Throwable $previous): self
    {
        $e = new self(
            'OpenAI API quota exceeded. Please check your OpenAI account billing.',
            0,
            $previous
        );
        $e->httpStatus = 429;
        return $e->setUserMessage('OpenAI quota exceeded. Check your OpenAI account billing.');
    }

    public static function invalidApiKey(Throwable $previous): self
    {
        $e = new self(
            'Invalid OpenAI API key. Please check your plugin settings.',
            0,
            $previous
        );
        $e->httpStatus = 503;
        return $e->setUserMessage('OpenAI rejected the request: the API key is invalid. Check your plugin settings.');
    }

    public static function apiError(string $message, Throwable $previous): self
    {
        $e = new self("Failed to generate embedding: {$message}", 0, $previous);
        return $e->setUserMessage('OpenAI embedding request failed. The administrator can find the technical details in the AI Search log.');
    }
}
