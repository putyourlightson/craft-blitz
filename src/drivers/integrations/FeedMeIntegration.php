<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\integrations;

/**
 * @deprecated in 5.7.0
 */
class FeedMeIntegration extends BaseIntegration
{
    /**
     * @inheritdoc
     */
    public static function getRequiredPlugins(): array
    {
        return [
            ['handle' => 'feed-me', 'version' => '4.0.0'],
        ];
    }
}
