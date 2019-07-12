<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\integrations;

use craft\feedme\services\Process;
use putyourlightson\blitz\Blitz;
use yii\base\Event;

class FeedMeIntegration implements IntegrationInterface
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function getRequiredPlugins(): array
    {
        return [
            ['handle' => 'feed-me', 'version' => '4.0.0']
        ];
    }

    /**
     * @inheritdoc
     */
    public static function registerEvents()
    {
        Event::on(Process::class, Process::EVENT_BEFORE_PROCESS_FEED,
            function() {
                Blitz::$plugin->refreshCache->batchMode = true;
            }
        );

        Event::on(Process::class, Process::EVENT_AFTER_PROCESS_FEED,
            function() {
                Blitz::$plugin->refreshCache->refresh();
            }
        );
    }
}
