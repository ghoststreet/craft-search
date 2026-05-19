<?php

namespace ghoststreet\craftsmartsearch\exceptions;

class ConfigurationException extends SmartSearchException
{
    public static function missingApiKey(string $service): self
    {
        $e = new self("{$service} API key is not configured.");
        $e->errorCode = ErrorCode::CONFIG_MISSING_API_KEY;
        return $e;
    }
}
