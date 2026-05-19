<?php

namespace ghoststreet\craftsmartsearch\assets;

use craft\web\AssetBundle;

class DataSyncAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->depends = [SmartSearchAsset::class];
        $this->js = ['js/pages/data-sync.js'];

        parent::init();
    }
}
