<?php

namespace ghoststreet\craftaisearch\helpers;

use Craft;

/**
 * Helper for extracting and validating common request parameters.
 *
 * Consolidates the duplicated parameter extraction pattern used
 * across search controller action methods.
 */
final class RequestParameterExtractor
{
    /**
     * Extract and validate common search parameters from the current request.
     *
     * @param int $defaultLimit Default result limit if not specified in request
     * @return array{query: string, limit: int, siteId: int|null, validationError: array|null}
     */
    public static function extractSearchParams(int $defaultLimit = 10): array
    {
        $request = Craft::$app->getRequest();

        $query = $request->getParam('q', '');
        $limit = ApiResponseHelper::validateLimit(
            (int)$request->getParam('limit', $defaultLimit)
        );
        $siteId = $request->getParam('siteId');
        $validationError = ApiResponseHelper::validateQuery($query);

        return [
            'query' => $query,
            'limit' => $limit,
            'siteId' => $siteId !== null ? (int)$siteId : null,
            'validationError' => $validationError,
        ];
    }
}
