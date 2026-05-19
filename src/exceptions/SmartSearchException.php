<?php

namespace ghoststreet\craftsmartsearch\exceptions;

use RuntimeException;

/**
 * Base exception for all Smart Search plugin errors.
 * Extend this class for domain-specific exceptions.
 */
abstract class SmartSearchException extends RuntimeException
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
