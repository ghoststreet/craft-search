<?php

namespace ghoststreet\craftaisearch\services;

use Craft;
use ghoststreet\craftaisearch\AiSearch;
use ghoststreet\craftaisearch\exceptions\SearchException;
use ghoststreet\craftaisearch\helpers\Logger;
use PDOException;
use yii\base\Component;

/**
 * BM25 Service using PostgreSQL native full-text search.
 *
 * Leverages ts_rank_cd with document-length normalization for BM25-style scoring,
 * combined with language-aware stemming via to_tsvector/to_tsquery. The text search
 * configuration is resolved automatically from the Craft site language. Results are
 * grouped by element so that the highest-scoring chunk represents each entry.
 */
class BM25Service extends Component
{
    /** ts_rank_cd normalization flag: divide rank by document length */
    private const TS_RANK_NORMALIZE_LENGTH = 32;

    /**
     * Calculate BM25-style scores for all entries matching the query using PostgreSQL full-text search.
     *
     * Stems the query into a tsquery OR expression, scores each chunk with ts_rank_cd
     * (length-normalized), and groups by element to return the maximum score per entry.
     *
     * @param string $query Raw search query text
     * @param int|null $siteId Restrict results to a specific site
     * @return array<int, array{elementId: int, siteId: int, bm25Score: float}> Scored entries ordered by relevance
     * @throws SearchException If the database query fails
     */
    public function calculateScores(string $query, ?int $siteId = null): array
    {
        $db = AiSearch::getInstance()->databaseService->getConnection();
        $normalizedQuery = trim($query);

        if ($normalizedQuery === '') {
            return [];
        }

        $tsQueryExpr = $this->buildTsQueryExpression($normalizedQuery);

        if ($tsQueryExpr === null) {
            return [];
        }

        $language = $this->resolveTextSearchConfig($siteId);
        $normalization = self::TS_RANK_NORMALIZE_LENGTH;

        try {
            $sql = "
                SELECT
                    \"elementId\",
                    \"siteId\",
                    MAX(ts_rank_cd(
                        to_tsvector('{$language}', COALESCE(content, '')),
                        to_tsquery('{$language}', :query),
                        {$normalization}
                    )) AS bm25_score
                FROM " . DatabaseService::TABLE_NAME . "
                WHERE to_tsvector('{$language}', COALESCE(content, '')) @@ to_tsquery('{$language}', :query)
            ";

            $params = [':query' => $tsQueryExpr];

            if ($siteId !== null) {
                $sql .= " AND \"siteId\" = :siteId";
                $params[':siteId'] = $siteId;
            }

            $sql .= " GROUP BY \"elementId\", \"siteId\"";

            $maxResults = AiSearch::getInstance()->getSettings()->maxSemanticResults;
            $sql .= " ORDER BY bm25_score DESC LIMIT {$maxResults}";

            Logger::debug('BM25 query', [
                'query' => $normalizedQuery,
                'siteId' => $siteId,
                'maxResults' => $maxResults,
            ]);

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            $rows = $stmt->fetchAll();

            Logger::debug('BM25 results', [
                'matchedElements' => count($rows),
            ]);

            $scores = [];
            foreach ($rows as $row) {
                $scores[] = [
                    'elementId' => (int)$row['elementId'],
                    'siteId' => (int)$row['siteId'],
                    'bm25Score' => (float)$row['bm25_score'],
                ];
            }

            return $scores;
        } catch (PDOException $e) {
            Logger::exception($e, 'calculateScores', ['query' => substr($query, 0, 50)]);
            throw SearchException::semanticSearchFailed('BM25 scoring failed', $e);
        }
    }

    /**
     * Resolve the PostgreSQL text search configuration from the Craft site language.
     *
     * Uses Locale::getDisplayLanguage() to convert the site's ISO code (e.g. "en-US")
     * into the English language name that PostgreSQL expects (e.g. "english").
     * Falls back to "simple" if the resolved name is not a valid PostgreSQL config.
     */
    private function resolveTextSearchConfig(?int $siteId): string
    {
        $site = $siteId !== null
            ? Craft::$app->getSites()->getSiteById($siteId)
            : Craft::$app->getSites()->getPrimarySite();

        $locale = $site->language ?? 'en';

        return strtolower(\Locale::getDisplayLanguage($locale, 'en'));
    }

    /**
     * Build a tsquery OR expression from raw query text.
     *
     * Splits on whitespace, strips tsquery-reserved characters while preserving
     * meaningful special characters within terms (e.g. "A+W"), and joins
     * surviving tokens with the OR operator (|).
     *
     * @return string|null The tsquery expression, or null if no valid terms remain
     */
    private function buildTsQueryExpression(string $query): ?string
    {
        $terms = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);

        $sanitized = [];
        foreach ($terms as $term) {
            // Strip characters that are tsquery operators or syntax: & | ! ( ) : * < >
            $clean = preg_replace('/[&|!():*<>]/', '', $term);
            // Remove leading/trailing non-alphanumeric chars (e.g. trailing punctuation)
            $clean = preg_replace('/^[^a-zA-Z0-9]+|[^a-zA-Z0-9]+$/', '', $clean);

            if ($clean !== '') {
                $sanitized[] = $clean;
            }
        }

        if (empty($sanitized)) {
            return null;
        }

        return implode(' | ', $sanitized);
    }
}
