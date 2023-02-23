<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\fixtures;

class AssetFixture extends \craft\test\fixtures\elements\AssetFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/assets.php';

    /**
     * @inheritdoc
     */
    public $depends = [VolumesFixture::class, VolumesFolderFixture::class];
}
