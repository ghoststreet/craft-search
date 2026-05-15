<?php

namespace ghoststreet\craftaisearch\controllers;

use Craft;
use ghoststreet\craftaisearch\AiSearch;
use ghoststreet\craftaisearch\exceptions\ErrorCode;
use ghoststreet\craftaisearch\helpers\ApiResponseHelper;
use yii\web\Response;

class SettingsController extends BaseApiController
{
    protected array|int|bool $allowAnonymous = false;

    public function actionIndex(): Response
    {
        $this->requireAdmin();

        return $this->renderTemplate('ai-search/settings', [
            'plugin' => AiSearch::getInstance(),
            'settings' => AiSearch::getInstance()->getSettings(),
            'selectedSubnavItem' => 'settings',
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        $plugin = AiSearch::getInstance();
        $settings = $plugin->getSettings();
        $settings->setAttributes(Craft::$app->getRequest()->getBodyParam('settings', []));

        if (!$settings->validate()
            || !Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray())
        ) {
            return $this->asModelFailure(
                $settings,
                Craft::t('ai-search', 'Could not save settings.'),
                'settings',
            );
        }

        return $this->asModelSuccess(
            $settings,
            Craft::t('ai-search', 'Settings saved.'),
            'settings',
        );
    }

    public function actionTestDatabaseConnection(): Response
    {
        $this->requireAdmin();
        $this->requireAcceptsJson();

        try {
            $db = AiSearch::getInstance()->databaseService;
            $db->getConnection();

            if (!$db->isSchemaInitialized()) {
                return $this->asJson([
                    'success' => false,
                    'code' => ErrorCode::DATABASE_TABLE_MISSING->value,
                    'requestId' => $this->requestId,
                ]);
            }

            return $this->asJson(['success' => true, 'requestId' => $this->requestId]);
        } catch (\Throwable $e) {
            return ApiResponseHelper::jsonError($this, $e, 'testDatabaseConnection', $this->errorContext());
        }
    }

    public function actionTestApiKey(): Response
    {
        $this->requireAdmin();
        $this->requireAcceptsJson();

        try {
            $client = AiSearch::getInstance()->openAIClientFactory->getClient();
            $client->models()->list();
            return $this->asJson(['success' => true, 'requestId' => $this->requestId]);
        } catch (\Throwable $e) {
            return ApiResponseHelper::jsonError($this, $e, 'testApiKey', $this->errorContext());
        }
    }
}
