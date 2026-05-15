<?php

namespace ghoststreet\craftaisearch\exceptions;

use Throwable;

class EmbeddingException extends AiSearchException
{
    public static function emptyText(): self
    {
        $e = new self('Cannot generate embedding: text cannot be empty');
        $e->errorCode = ErrorCode::EMBEDDING_EMPTY_TEXT;
        return $e;
    }

    public static function rateLimited(Throwable $previous): self
    {
        $e = new self('OpenAI API rate limit exceeded.', 0, $previous);
        $e->errorCode = ErrorCode::EMBEDDING_RATE_LIMITED;
        return $e;
    }

    public static function quotaExceeded(Throwable $previous): self
    {
        $e = new self('OpenAI API quota exceeded.', 0, $previous);
        $e->errorCode = ErrorCode::EMBEDDING_QUOTA_EXCEEDED;
        return $e;
    }

    public static function invalidApiKey(Throwable $previous): self
    {
        $e = new self('Invalid OpenAI API key.', 0, $previous);
        $e->errorCode = ErrorCode::EMBEDDING_INVALID_API_KEY;
        return $e;
    }

    public static function apiError(string $message, Throwable $previous): self
    {
        $e = new self("Failed to generate embedding: {$message}", 0, $previous);
        $e->errorCode = ErrorCode::EMBEDDING_API_ERROR;
        return $e;
    }
}
