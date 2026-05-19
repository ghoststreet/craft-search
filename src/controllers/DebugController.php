<?php

namespace ghoststreet\craftsmartsearch\controllers;

use Craft;
use craft\web\Controller;
use ghoststreet\craftsmartsearch\SmartSearch;
use ghoststreet\craftsmartsearch\exceptions\DatabaseException;
use ghoststreet\craftsmartsearch\helpers\ErrorMapper;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Debug controller: per-entry inspection of indexing behavior.
 */
class DebugController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requireAdmin();

        $request = Craft::$app->getRequest();
        $plugin = SmartSearch::getInstance();
        $settings = $plugin->getSettings();

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
            $error = ErrorMapper::present($e, 'debug.getEntryRows', ['siteId' => $filters['siteId']]);
        }

        return $this->renderTemplate('smart-search/debug/index', [
            'plugin' => $plugin,
            'settings' => $settings,
            'result' => $result,
            'filters' => $filters,
            'sections' => Craft::$app->getEntries()->getAllSections(),
            'sites' => Craft::$app->getSites()->getAllSites(),
            'error' => $error,
            'selectedSubnavItem' => 'debug',
        ]);
    }

    public function actionEntry(): Response
    {
        $this->requireAdmin();

        $request = Craft::$app->getRequest();
        $elementId = (int)$request->getRequiredQueryParam('elementId');
        $siteId = (int)$request->getRequiredQueryParam('siteId');

        $plugin = SmartSearch::getInstance();

        try {
            $inspection = $plugin->indexingDebugService->inspectElement($elementId, $siteId);
            $error = null;
        } catch (DatabaseException $e) {
            $inspection = null;
            $error = ErrorMapper::present($e, 'debug.inspectElement', ['elementId' => $elementId, 'siteId' => $siteId]);
        }

        if ($inspection === null && $error === null) {
            throw new NotFoundHttpException('Entry not found');
        }

        return $this->renderTemplate('smart-search/debug/entry', [
            'plugin' => $plugin,
            'inspection' => $inspection,
            'error' => $error,
            'selectedSubnavItem' => 'debug',
        ]);
    }
}
