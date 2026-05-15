<?php

namespace ghoststreet\craftaisearch\exceptions;

class ConfigurationException extends AiSearchException
{
    public static function missingApiKey(string $service): self
    {
        $e = new self("{$service} API key is not configured.");
        $e->errorCode = ErrorCode::CONFIG_MISSING_API_KEY;
        return $e;
    }
}
