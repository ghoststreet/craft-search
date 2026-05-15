<?php

namespace ghoststreet\craftaisearch\assets;

use craft\web\AssetBundle;

class InsightsAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->depends = [CraftSearchAsset::class];
        $this->js = [
            'vendor/chart.umd.min.js',
            'js/core/chart-theme.js',
            'js/components/chart.js',
            'js/pages/insights.js',
        ];

        parent::init();
    }
}
