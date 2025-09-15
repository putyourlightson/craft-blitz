<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\assets;

use craft\web\AssetBundle;

class BlitzInjectScriptAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = '@putyourlightson/blitz/resources';

        $this->js = [
            'js/blitzInjectScript.js',
        ];

        parent::init();
    }
}
