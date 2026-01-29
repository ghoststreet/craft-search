<?php

namespace ghoststreet\craftaisearch\controllers;

use craft\web\Controller;
use ghoststreet\craftaisearch\AiSearch;
use yii\web\Response;

/**
 * Dashboard controller displaying plugin statistics and connection status.
 */
class DashboardController extends Controller
{
    /**
     * Render the AI Search dashboard with stats and settings overview.
     */
    public function actionIndex(): Response
    {
        $plugin = AiSearch::getInstance();

        return $this->renderTemplate('ai-search/index', [
            'plugin' => $plugin,
            'settings' => $plugin->getSettings(),
            'stats' => $plugin->databaseService->getStats(),
            'selectedSubnavItem' => 'dashboard',
        ]);
    }
}
