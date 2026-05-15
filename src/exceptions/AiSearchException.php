<?php

namespace ghoststreet\craftaisearch\exceptions;

use RuntimeException;

/**
 * Base exception for all AI Search plugin errors.
 * Extend this class for domain-specific exceptions.
 */
abstract class AiSearchException extends RuntimeException
{
    protected ErrorCode $errorCode = ErrorCode::UNKNOWN;

    public function errorCode(): ErrorCode
    {
        return $this->errorCode;
    }

    public function httpStatus(): int
    {
        return $this->errorCode->httpStatus();
    }
}
