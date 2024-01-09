<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use craft\i18n\Translation;

class m240109_120000_add_diagnostics_announcement extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        Craft::$app->announcements->push(
            Translation::prep('blitz', 'New Diagnostics Utility'),
            Translation::prep('blitz', 'The new Blitz Diagnostics utility helps you better understand how your site’s cached content is structured, allowing you to optimise the caching strategy and overall performance of your site ([read the announcement]({url})).', [
                'url' => 'https://putyourlightson.com/articles/introducing-blitz-diagnostics',
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
