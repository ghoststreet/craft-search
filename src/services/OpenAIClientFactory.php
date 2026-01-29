<?php

namespace ghoststreet\craftaisearch\services;

use ghoststreet\craftaisearch\AiSearch;
use ghoststreet\craftaisearch\exceptions\ConfigurationException;
use OpenAI;
use OpenAI\Client;
use yii\base\Component;

/**
 * Factory for creating and caching OpenAI client instances.
 * Ensures a single client instance is reused across all services.
 */
class OpenAIClientFactory extends Component
{
    private ?Client $client = null;

    /**
     * Get the OpenAI client instance.
     * Creates the client on first call and caches it for subsequent calls.
     *
     * @throws ConfigurationException If API key is not configured
     */
    public function getClient(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $settings = AiSearch::getInstance()->getSettings();
        $apiKey = $settings->getOpenaiApiKey();

        if ($apiKey === null || $apiKey === '') {
            throw ConfigurationException::missingApiKey('OpenAI');
        }

        $this->client = OpenAI::client($apiKey);

        return $this->client;
    }
}
