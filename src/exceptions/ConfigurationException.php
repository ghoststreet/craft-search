<?php

namespace ghoststreet\craftaisearch\exceptions;

/**
 * Thrown when required plugin settings are missing or invalid.
 */
class ConfigurationException extends AiSearchException
{
    public static function missingApiKey(string $service): self
    {
        return new self("{$service} API key is not configured. Please set it in plugin settings.");
    }
}
