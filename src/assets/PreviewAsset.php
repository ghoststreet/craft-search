<?php

namespace ghoststreet\craftsmartsearch\assets;

use craft\web\AssetBundle;

class PreviewAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->depends = [SmartSearchAsset::class];
        $this->css = ['css/pages/preview.css'];
        $this->js = ['js/pages/preview.js'];

        parent::init();
    }
}
