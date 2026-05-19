<?php

namespace ghoststreet\craftaisearch\controllers;

use Craft;
use craft\web\Controller;
use ghoststreet\craftaisearch\AiSearch;
use yii\web\Response;

class PreviewController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requireAdmin();

        $plugin = AiSearch::getInstance();
        $sites = Craft::$app->getSites()->getAllSites();

        return $this->renderTemplate('ai-search/preview/index', [
            'plugin' => $plugin,
            'sites' => $sites,
            'selectedSubnavItem' => 'preview',
        ]);
    }
}
