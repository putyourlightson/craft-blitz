<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\fixtures;

use craft\records\Section_SiteSettings;
use craft\test\Fixture;

class SectionSettingFixture extends Fixture
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
