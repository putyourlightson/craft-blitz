<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\integrations;

use craft\feedme\services\Process;
use putyourlightson\blitz\Blitz;
use yii\base\Event;

class FeedMeIntegration extends BasePluginIntegration
{
    // Constants
    // =========================================================================

    const PLUGIN_HANDLE = 'feed-me';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!$this->isPluginInstalled()) {
            return;
        }

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
