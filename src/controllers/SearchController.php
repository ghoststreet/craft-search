<?php

namespace ghoststreet\craftaisearch\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use ghoststreet\craftaisearch\AiSearch;
use ghoststreet\craftaisearch\exceptions\AiSearchException;
use ghoststreet\craftaisearch\helpers\ApiResponseHelper;
use ghoststreet\craftaisearch\helpers\RequestParameterExtractor;
use ghoststreet\craftaisearch\helpers\SearchResultFormatter;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

/**
 * Search controller
 */
class SearchController extends Controller
{
    public $defaultAction = 'semantic-search';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    /**
     * Validate API token before processing any search action.
     * Fails closed: if no token is configured in settings, all requests are rejected.
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $token = AiSearch::getInstance()->getSettings()->getApiToken();

        if (empty($token)) {
            throw new UnauthorizedHttpException('API token is not configured.');
        }

        $request = Craft::$app->getRequest();
        $authorization = (string)$request->getHeaders()->get('Authorization');
        $presented = str_starts_with($authorization, 'Bearer ')
            ? substr($authorization, 7)
            : (string)$request->getQueryParam('token');

        if ($presented !== '' && hash_equals($token, $presented)) {
            return true;
        }

        throw new UnauthorizedHttpException('Invalid or missing API token.');
    }

    /**
     * Craft default search API endpoint
     *
     * @return Response
     */
    public function actionCraftSearch(): Response
    {
        $params = RequestParameterExtractor::extractSearchParams();

        if ($params['validationError'] !== null) {
            return $this->asJson($params['validationError'])->setStatusCode(400);
        }

        try {
            $searchQuery = Entry::find();

            if ($params['siteId'] !== null) {
                $searchQuery->siteId($params['siteId']);
            }

            $searchQuery->status(Entry::STATUS_ENABLED)
                ->search($params['query'])
                ->limit($params['limit']);

            $entries = $searchQuery->all();

            $formattedResults = $this->formatElementResults($entries, [], SearchResultFormatter::TYPE_CRAFT);

            return $this->asJson([
                'success' => true,
                'query' => $params['query'],
                'results' => array_values($formattedResults),
                'count' => count($formattedResults),
            ]);
        } catch (AiSearchException $e) {
            return $this->asJson(ApiResponseHelper::error($e))->setStatusCode(500);
        }
    }

    /**
     * Semantic search API endpoint
     *
     * @return Response
     */
    public function actionSemanticSearch(): Response
    {
        $params = RequestParameterExtractor::extractSearchParams();
        $hybrid = (bool)Craft::$app->getRequest()->getParam('hybrid', true);

        if ($params['validationError'] !== null) {
            return $this->asJson($params['validationError'])->setStatusCode(400);
        }

        try {
            $results = AiSearch::getInstance()->searchService->search(
                $params['query'],
                $params['limit'],
                $params['siteId'],
                $hybrid
            );

            $resultType = $hybrid ? SearchResultFormatter::TYPE_HYBRID : SearchResultFormatter::TYPE_SEMANTIC;
            $formattedResults = $this->formatSearchResults($results, $resultType);

            return $this->asJson([
                'success' => true,
                'query' => $params['query'],
                'hybrid' => $hybrid,
                'semanticResults' => array_values($formattedResults),
                'semanticCount' => count($formattedResults),
            ]);
        } catch (AiSearchException $e) {
            return $this->asJson(ApiResponseHelper::error($e))->setStatusCode(500);
        }
    }

    /**
     * RAG search API endpoint with AI summary
     * Uses hybrid search + OpenAI for intelligent responses
     *
     * @return Response
     */
    public function actionRagSearch(): Response
    {
        $params = RequestParameterExtractor::extractSearchParams(5);

        if ($params['validationError'] !== null) {
            return $this->asJson($params['validationError'])->setStatusCode(400);
        }

        try {
            $response = AiSearch::getInstance()->ragSearchService->search(
                $params['query'],
                $params['limit'],
                $params['siteId']
            );

            $formattedSources = $this->formatElementResults(
                array_column($response['sources'], 'element'),
                $response['sources'],
                SearchResultFormatter::TYPE_RAG
            );

            return $this->asJson([
                'success' => true,
                'query' => $params['query'],
                'summary' => $response['summary'] ?? null,
                'sources' => $formattedSources,
                'count' => count($formattedSources),
                'confidence' => $response['confidence'] ?? null,
            ]);
        } catch (AiSearchException $e) {
            return $this->asJson(ApiResponseHelper::error($e))->setStatusCode(500);
        }
    }

    /**
     * Format a list of elements with optional metadata.
     */
    private function formatElementResults(array $elements, array $metadataList, string $type): array
    {
        $formatted = [];

        foreach ($elements as $index => $element) {
            $metadata = $metadataList[$index] ?? [];
            $result = SearchResultFormatter::format($element, $metadata, $type);

            if ($result !== null) {
                $formatted[] = $result;
            }
        }

        return $formatted;
    }

    /**
     * Format search results with excerpt generation.
     */
    private function formatSearchResults(array $results, string $type): array
    {
        $formatted = [];

        foreach ($results as $result) {
            $metadata = array_merge($result, [
                'excerpt' => SearchResultFormatter::getExcerptFromContent(
                    $result['content'] ?? '',
                    $result['element']?->title
                ),
            ]);

            $item = SearchResultFormatter::format($result['element'], $metadata, $type);

            if ($item !== null) {
                $formatted[] = $item;
            }
        }

        return $formatted;
    }
}
