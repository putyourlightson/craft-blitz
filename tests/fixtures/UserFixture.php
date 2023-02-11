<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\fixtures;

class UserFixture extends \craft\test\fixtures\elements\UserFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/users.php';

    /**
     * @inheritdoc
     */
    public $depends = [FieldLayoutFixture::class];
}
