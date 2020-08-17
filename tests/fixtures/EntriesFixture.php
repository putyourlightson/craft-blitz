<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\fixtures;

use craft\test\fixtures\elements\EntryFixture;

class EntriesFixture extends EntryFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/entries.php';

    /**
     * @inheritdoc
     */
    public $depends = [SectionsFixture::class, EntryTypeFixture::class];
}
