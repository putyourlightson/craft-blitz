<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\Blitz;

class m220330_120000_update_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.blitz.schemaVersion', true);

        if (version_compare($schemaVersion, '4.0.0', '<')) {
            $clearCacheAutomatically = $projectConfig->get('plugins.blitz.settings.clearCacheAutomatically') ?? true;
            $warmCacheAutomatically = $projectConfig->get('plugins.blitz.settings.warmCacheAutomatically') ?? true;
            $invalidationMode = $this->_getInvalidationMode($clearCacheAutomatically, $warmCacheAutomatically);
            $projectConfig->set('plugins.blitz.settings.invalidationMode', $invalidationMode);

            $includedQueryStringParams = $projectConfig->get('plugins.blitz.settings.includedQueryStringParams');
            $this->_updateQueryStringParams($includedQueryStringParams);
            $projectConfig->set('plugins.blitz.settings.includedQueryStringParams', $includedQueryStringParams);

            $excludedQueryStringParams = $projectConfig->get('plugins.blitz.settings.excludedQueryStringParams');
            $this->_updateQueryStringParams($excludedQueryStringParams);
            $projectConfig->set('plugins.blitz.settings.excludedQueryStringParams', $excludedQueryStringParams);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return false;
    }

    private function _getInvalidationMode(bool $clear, bool $warm): int
    {
        return $clear ? ($warm ? 3 : 1) : ($warm ? 2 : 0);
    }

    private function _updateQueryStringParams(array &$queryStringParams)
    {
        // Add keys to query string params
        foreach ($queryStringParams as $key => $queryStringParam) {
            if (is_string($queryStringParam)) {
                $queryStringParams[$key] = [
                    'siteId' => '',
                    'queryStringParam' => $queryStringParam,
                ];
            }
        }
    }
}
