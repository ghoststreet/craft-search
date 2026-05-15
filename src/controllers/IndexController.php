<?php

namespace ghoststreet\craftaisearch\controllers;

use Craft;
use craft\elements\Entry;
use ghoststreet\craftaisearch\AiSearch;
use ghoststreet\craftaisearch\exceptions\DatabaseException;
use ghoststreet\craftaisearch\helpers\ErrorMapper;
use ghoststreet\craftaisearch\helpers\Logger;
use ghoststreet\craftaisearch\jobs\IndexEntryJob;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Index management page: tabs for overview/sync, entries (debug), and coverage.
 * Replaces the previous Data Sync + Debug pages.
 */
class IndexController extends BaseApiController
{
    protected array|int|bool $allowAnonymous = false;

    public function actionIndex(): Response
    {
        $this->requireAdmin();

        $request = Craft::$app->getRequest();
        $tab = $request->getQueryParam('tab') ?: 'overview';
        $plugin = AiSearch::getInstance();
        $settings = $plugin->getSettings();
        $stats = $plugin->databaseService->getStatsSafe();

        $hasCredentials = !empty($settings->getPostgresqlHost())
            && !empty($settings->getPostgresqlDatabase())
            && !empty($settings->getPostgresqlUser())
            && !empty($settings->getPostgresqlPassword());

        $setup = [
            'credentials' => $hasCredentials,
            'connection' => (bool)($stats['isConnected'] ?? false),
            'schema' => ($stats['isConnected'] ?? false) ? $plugin->databaseService->isSchemaInitialized() : false,
            'openaiKey' => !empty($settings->getOpenaiApiKey()),
            'error' => $stats['error'] ?? null,
        ];
        $setup['ready'] = $setup['credentials'] && $setup['connection'] && $setup['schema'] && $setup['openaiKey'];

        $data = [
            'tab' => $tab,
            'plugin' => $plugin,
            'settings' => $settings,
            'stats' => $stats,
            'setup' => $setup,
            'syncStarted' => Craft::$app->getSession()->getFlash('ai-search-sync-started', false),
            'selectedSubnavItem' => 'index',
        ];

        if ($tab === 'entries') {
            $filters = [
                'section' => $request->getQueryParam('section') ?: null,
                'siteId' => (int)($request->getQueryParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id),
                'status' => $request->getQueryParam('status') ?: null,
                'page' => (int)($request->getQueryParam('page') ?: 1),
            ];

            try {
                $result = $plugin->indexingDebugService->getEntryRows($filters);
                $error = null;
            } catch (DatabaseException $e) {
                $result = ['rows' => [], 'total' => 0, 'page' => 1, 'pageSize' => 50, 'counts' => ['indexed' => 0, 'stale' => 0, 'not-indexed' => 0, 'total' => 0]];
                $error = ErrorMapper::present($e, 'getEntryRows', ['siteId' => $filters['siteId']]);
            }

            $data['result'] = $result;
            $data['filters'] = $filters;
            $data['sections'] = Craft::$app->getEntries()->getAllSections();
            $data['sites'] = Craft::$app->getSites()->getAllSites();
            $data['error'] = $error;
        } elseif ($tab === 'coverage') {
            $data['coverage'] = $plugin->indexingDebugService->getCoverageBySite();
        }

        return $this->renderTemplate('ai-search/index-mgmt/index', $data);
    }

    public function actionEntry(): Response
    {
        $this->requireAdmin();

        $request = Craft::$app->getRequest();
        $elementId = (int)$request->getRequiredQueryParam('elementId');
        $siteId = (int)$request->getRequiredQueryParam('siteId');

        $plugin = AiSearch::getInstance();

        try {
            $inspection = $plugin->indexingDebugService->inspectElement($elementId, $siteId);
            $error = null;
        } catch (DatabaseException $e) {
            $inspection = null;
            $error = ErrorMapper::present($e, 'inspectElement', ['elementId' => $elementId, 'siteId' => $siteId]);
        }

        if ($inspection === null && $error === null) {
            throw new NotFoundHttpException('Entry not found');
        }

        return $this->renderTemplate('ai-search/index-mgmt/entry', [
            'plugin' => $plugin,
            'inspection' => $inspection,
            'error' => $error,
            'selectedSubnavItem' => 'index',
        ]);
    }

    public function actionWipeAndReindex(): Response
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        try {
            Logger::info('Starting wipe-and-reindex operation');

            AiSearch::getInstance()->databaseService->clearAllVectors();

            $entries = Entry::find()
                ->siteId('*')
                ->unique(false)
                ->status(Entry::STATUS_ENABLED)
                ->uri(':notempty:')
                ->select(['elements.id', 'elements_sites.siteId'])
                ->asArray()
                ->all();

            $queue = Craft::$app->getQueue();
            foreach ($entries as $entry) {
                $queue->push(new IndexEntryJob([
                    'entryId' => (int)$entry['id'],
                    'siteId' => (int)$entry['siteId'],
                ]));
            }

            $count = count($entries);
            Logger::info('Queued entries for sync', ['count' => $count]);

            Craft::$app->getSession()->setFlash('ai-search-sync-started', true);
            Craft::$app->getSession()->setNotice(
                Craft::t('ai-search', 'Search index cleared. {count} entries queued for reindexing.', ['count' => $count])
            );
        } catch (DatabaseException $e) {
            Craft::$app->getSession()->setError(
                Craft::t('ai-search', 'Failed to start sync: {error}', ['error' => ErrorMapper::present($e, 'syncReindex')])
            );
        }

        return $this->redirect('ai-search/index');
    }

    public function actionGetStats(): Response
    {
        $this->requireAdmin();
        $this->requireAcceptsJson();

        try {
            $stats = AiSearch::getInstance()->databaseService->getStats(false);
            $queueTotal = Craft::$app->getQueue()->getTotalWaiting();

            return $this->asJson([
                'success' => true,
                'entryCount' => $stats['entryCount'],
                'chunkCount' => $stats['chunkCount'],
                'queueRemaining' => $queueTotal,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError($e, 'getStats');
        }
    }

    public function actionLegacyRedirect(): Response
    {
        return $this->redirect('ai-search/index');
    }
}
