<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\db\Migration;
use putyourlightson\blitz\drivers\generators\GuzzleGenerator;
use putyourlightson\blitz\drivers\generators\LocalGenerator;

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
            $warmerType = $projectConfig->get('plugins.blitz.settings.cacheWarmerType') ?? null;
            $generatorType = $this->_getGeneratorType($warmerType);
            $projectConfig->set('plugins.blitz.settings.cacheGeneratorType', $generatorType);

            $generatorSettings = $projectConfig->get('plugins.blitz.settings.cacheWarmerSettings') ?? [];
            $projectConfig->set('plugins.blitz.settings.cacheGeneratorSettings', $generatorSettings);

            $clear = $projectConfig->get('plugins.blitz.settings.clearCacheAutomatically') ?? true;
            $generate = $projectConfig->get('plugins.blitz.settings.warmCacheAutomatically') ?? true;
            $refreshMode = $this->_getRefreshMode($clear, $generate);
            $projectConfig->set('plugins.blitz.settings.refreshMode', $refreshMode);

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

    private function _getGeneratorType(string $warmerType): string
    {
        return $warmerType == 'putyourlightson\\blitz\\drivers\\warmers\\LocalWarmer'
            ? LocalGenerator::class : GuzzleGenerator::class;
    }

    private function _getRefreshMode(bool $clear, bool $generate): int
    {
        return $clear ? ($generate ? 3 : 1) : ($generate ? 2 : 0);
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
