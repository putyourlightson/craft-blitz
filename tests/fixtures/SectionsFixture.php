<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\fixtures;

use Craft;
use craft\records\Section;
use craft\services\Sections;
use craft\test\Fixture;

class SectionsFixture extends Fixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/sections.php';

    /**
     * @inheritdoc
     */
    public $modelClass = Section::class;

    /**
     * @inheritdoc
     */
    public $depends = [SectionSettingFixture::class];

    /**
     * @inheritdoc
     */
    public function load()
    {
        parent::load();

        Craft::$app->set('sections', new Sections());
    }
}
