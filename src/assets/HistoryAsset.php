<?php

namespace ghoststreet\craftsmartsearch\assets;

use craft\web\AssetBundle;

class HistoryAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->depends = [SmartSearchAsset::class];
        $this->js = ['js/pages/history.js'];

        parent::init();
    }
}
