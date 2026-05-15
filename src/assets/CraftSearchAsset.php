<?php

namespace ghoststreet\craftaisearch\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Base CP asset bundle for the Craft Search plugin.
 * Bootstraps the window.CraftSearch namespace and shared core modules.
 * Page-specific bundles depend on this and add their own components/pages.
 */
class CraftSearchAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->depends = [CpAsset::class];
        $this->js = [
            'js/craft-search-base.js',
            'js/craft-search-config.js',
            'js/core/dom.js',
            'js/core/api.js',
            'js/core/utils.js',
        ];
        $this->css = [
            'css/dashboard.css',
            'css/debug.css',
        ];

        parent::init();
    }
}
