<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use craft\i18n\Translation;

class m220517_120000_add_hints_announcement extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        Craft::$app->announcements->push(
            Translation::prep('blitz', 'New Hints Utility'),
            Translation::prep('blitz', 'The new Blitz Hints utility displays templating performance hints for eager-loading elements in your Twig templates. [Read the announcement]({url}) →', [
                'url' => 'https://putyourlightson.com/articles/ballroom-blitz',
            ]),
            'blitz',
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return true;
    }
}
