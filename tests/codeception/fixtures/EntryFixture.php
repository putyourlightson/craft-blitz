<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\fixtures;

class EntryFixture extends \craft\test\fixtures\elements\EntryFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/entries.php';

    /**
     * @inheritdoc
     */
    public $depends = [FieldLayoutFixture::class, SectionsFixture::class, EntryTypeFixture::class];
}
