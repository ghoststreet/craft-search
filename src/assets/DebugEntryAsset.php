<?php

namespace ghoststreet\craftsmartsearch\assets;

use craft\web\AssetBundle;

class DebugEntryAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->depends = [SmartSearchAsset::class];
        $this->js = ['js/pages/debug-entry.js'];

        parent::init();
    }
}
