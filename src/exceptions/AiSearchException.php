<?php

namespace ghoststreet\craftaisearch\exceptions;

use RuntimeException;

/**
 * Base exception for all AI Search plugin errors.
 * Extend this class for domain-specific exceptions.
 */
abstract class AiSearchException extends RuntimeException
{
    protected int $httpStatus = 500;

    protected ?string $userMessage = null;

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function userMessage(): string
    {
        return $this->userMessage ?? $this->getMessage();
    }

    public function setUserMessage(string $message): static
    {
        $this->userMessage = $message;
        return $this;
    }
}
