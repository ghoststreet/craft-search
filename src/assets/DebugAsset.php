<?php

namespace ghoststreet\craftsmartsearch\assets;

use craft\web\AssetBundle;

class DebugAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->depends = [SmartSearchAsset::class];
        $this->js = ['js/pages/debug.js'];

        parent::init();
    }
}
