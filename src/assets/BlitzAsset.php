<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class BlitzAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = '@putyourlightson/blitz/resources';

        $this->depends = [
            CpAsset::class,
        ];

        $this->css = [
            'css/cp.css',
        ];

        $this->js = [
            'js/cp.js',
        ];

        parent::init();
    }
}
