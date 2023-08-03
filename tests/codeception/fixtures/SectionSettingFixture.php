<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\fixtures;

use craft\records\Section_SiteSettings;
use craft\test\ActiveFixture;

class SectionSettingFixture extends ActiveFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/section-settings.php';

    /**
     * @inheritdoc
     */
    public $modelClass = Section_SiteSettings::class;
}
