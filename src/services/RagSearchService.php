<?php

namespace ghoststreet\craftaisearch\services;

use ghoststreet\craftaisearch\AiSearch;
use ghoststreet\craftaisearch\exceptions\AiSearchException;
use ghoststreet\craftaisearch\exceptions\SearchException;
use ghoststreet\craftaisearch\helpers\Logger;
use ghoststreet\craftaisearch\helpers\TimingProfiler;
use ghoststreet\craftaisearch\models\Settings;
use RuntimeException;
use yii\base\Component;

/**
 * RAG Search Service — Retrieval-Augmented Generation implementation.
 *
 * Performs a hybrid search to find relevant entries, builds a structured context
 * from those results, then sends the query and context to an LLM to generate
 * a conversational summary with source attribution.
 */
class RagSearchService extends Component
{
    /** Models that do not support the temperature parameter */
    private const MODELS_WITHOUT_TEMPERATURE = ['gpt-5-nano'];

    /**
     * Perform AI-powered search: hybrid retrieval followed by LLM summary generation.
     *
     * @param string $query The user's search query
     * @param int $limit Maximum number of source entries to include
     * @param int|null $siteId Restrict search to a specific site
     * @return array{summary: string, sources: array, confidence: string, rag: true}
     * @throws SearchException If search or summary generation fails
     */
    public function search(string $query, int $limit = 5, ?int $siteId = null): array
    {
        return TimingProfiler::profile('TOTAL RAG search', function() use ($query, $limit, $siteId) {
            try {
                $settings = AiSearch::getInstance()->getSettings();

                $searchResults = TimingProfiler::profile(
                    'Hybrid search',
                    fn() => AiSearch::getInstance()->hybridSearchService->search(
                        $query,
                        $limit,
                        $siteId,
                        $settings->ragEmbeddingModel
                    )
                );

                if (empty($searchResults)) {
                    return [
                        'summary' => 'No relevant results found for your query.',
                        'sources' => [],
                        'confidence' => 'low',
                        'rag' => true,
                    ];
                }

                $context = TimingProfiler::profile(
                    'Context building',
                    fn() => $this->buildContext($searchResults)
                );

                Logger::debug('Context built', ['length' => strlen($context)]);

                $llmResponse = TimingProfiler::profile(
                    'LLM summary generation',
                    fn() => $this->generateSummary($query, $context, $settings)
                );

                return $this->parseResponse($llmResponse, $searchResults, $limit);
            } catch (AiSearchException $e) {
                Logger::exception($e, 'ragSearch', ['query' => substr($query, 0, 50)]);
                throw SearchException::ragSearchFailed($e->getMessage(), $e);
            }
        });
    }

    /**
     * Build a structured context string from search results for LLM consumption.
     *
     * Each source is formatted as a labelled block with ID, title, URL, and content
     * so the LLM can reference specific sources in its response.
     */
    private function buildContext(array $searchResults): string
    {
        $contextBlocks = [];

        foreach ($searchResults as $index => $result) {
            $element = $result['element'];
            $content = $result['content'] ?? '';
            $sourceNumber = $index + 1;

            $contextBlocks[] = "---\nSOURCE {$sourceNumber}\nID: {$element->id}\nTitle: {$element->title}\nURL: {$element->getUrl()}\nContent:\n{$content}\n---";
        }

        return implode("\n\n", $contextBlocks);
    }

    /**
     * Send the query and context to the configured LLM and return the raw response content.
     *
     * @throws RuntimeException If the LLM API call fails
     */
    private function generateSummary(string $query, string $context, Settings $settings): string
    {
        $client = AiSearch::getInstance()->openAIClientFactory->getClient();

        $systemPrompt = $this->buildSystemPrompt($settings);

        $userPrompt = "Query: \"{$query}\"\n\n";
        $userPrompt .= "Here are the search results:\n\n{$context}\n\n";
        $userPrompt .= "Based on these sources, provide a helpful answer to the query. ";
        $userPrompt .= "Return your response as JSON: {\"summary\": \"your answer\", \"sourceIds\": [id1, id2], \"confidence\": \"high|medium|low\"}";

        $params = [
            'model' => $settings->ragModel,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        if (!in_array($settings->ragModel, self::MODELS_WITHOUT_TEMPERATURE)) {
            $params['temperature'] = $settings->ragTemperature;
        }

        $response = $client->chat()->create($params);

        return $response->choices[0]->message->content;
    }

    /**
     * Builds a system prompt that instructs the LLM to produce conversational summaries
     * with source attribution. The prompt enforces JSON output format so parseResponse()
     * can extract structured data. Appends any user-defined ragCustomPrompt from settings.
     */
    private function buildSystemPrompt(Settings $settings): string
    {
        $prompt = <<<PROMPT
You are a friendly and knowledgeable assistant helping users discover information. Write like you're having a conversation - warm, natural, and engaging.

## Your Style
- Write in a conversational, human tone - not robotic or formulaic
- Weave information naturally into flowing sentences and paragraphs
- Avoid bullet points and rigid structures - tell a story instead
- Be enthusiastic when the content is exciting
- Include helpful context that makes the answer richer

## How to Respond
- Read all sources carefully and synthesize the information naturally
- Include specific details (dates, locations, names) but integrate them smoothly
- If there's interesting background or context, share it
- Reference sources naturally (e.g., "According to the press release..." or "The event page mentions...")

## Response Format
Return a JSON object:
- summary: Your conversational answer. Use \\n for paragraphs. Write 2-4 sentences minimum.
- sourceIds: Array of source IDs used (e.g., [123, 456])
- confidence: "high", "medium", or "low"

## Important
- Never make up information - only use what's in the sources
- If information is incomplete, say so naturally
PROMPT;

        if (!empty($settings->ragCustomPrompt)) {
            $prompt .= "\n\n## Additional Instructions\n" . trim($settings->ragCustomPrompt);
        }

        return $prompt;
    }

    /**
     * Parse the LLM JSON response, extract source references, and build the final result.
     *
     * @throws SearchException If the LLM response is not valid JSON with a "summary" key
     */
    private function parseResponse(string $content, array $searchResults, int $limit): array
    {
        if (!preg_match('/\{[^{}]*"summary"[^{}]*\}/s', $content, $match)) {
            throw SearchException::ragSearchFailed(
                sprintf('LLM response did not contain expected JSON format. Preview: %s', substr($content, 0, 200)),
                new RuntimeException('Malformed LLM response')
            );
        }

        $parsed = json_decode($match[0], true);

        if ($parsed === null) {
            throw SearchException::ragSearchFailed(
                sprintf('Failed to parse LLM JSON response: %s', json_last_error_msg()),
                new RuntimeException('JSON parse error')
            );
        }

        $sourceIds = $parsed['sourceIds'] ?? [];
        $filteredSources = [];

        foreach ($searchResults as $result) {
            if (in_array($result['element']->id, $sourceIds)) {
                $filteredSources[] = $result;
            }
        }

        return [
            'summary' => $parsed['summary'],
            'sources' => $this->buildSourceList($filteredSources, $limit),
            'confidence' => $parsed['confidence'] ?? 'medium',
            'rag' => true,
        ];
    }

    /**
     * Build the source list for the RAG response, limited to the top N results.
     *
     * @param array $results Filtered search results containing elements
     * @param int $limit Maximum number of sources to include
     * @return array<int, array{element: mixed, id: int, ragRank: int}>
     */
    private function buildSourceList(array $results, int $limit): array
    {
        $sources = [];

        foreach (array_slice($results, 0, $limit) as $result) {
            $element = $result['element'];
            $sources[] = [
                'element' => $element,
                'id' => $element->id,
                'ragRank' => count($sources) + 1,
            ];
        }

        return $sources;
    }
}
