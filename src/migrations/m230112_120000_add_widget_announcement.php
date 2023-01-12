<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use craft\i18n\Translation;

class m230112_120000_add_widget_announcement extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        Craft::$app->announcements->push(
            Translation::prep('blitz', 'New Dashboard Widget'),
            Translation::prep('blitz', 'The new Blitz Cache dashboard widget contains the ability to refresh specific URIs, all pages in a site, or the entire cache ([read the announcement]({url})).', [
                'url' => 'https://putyourlightson.com/articles/blitz-4-3-feature-release',
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
