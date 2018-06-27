<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\utilities;

use Craft;
use craft\base\Utility;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SettingsModel;

class ClearCacheUtility extends Utility
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Clear Blitz Cache');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'clear-blitz-cache';
    }

    /**
     * @inheritdoc
     */
    public static function iconPath()
    {
        return Craft::getAlias('@app/icons/trash.svg');
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        /** @var SettingsModel $settings */
        $settings = Blitz::$plugin->getSettings();

        $options = [];

        foreach (Blitz::$plugin->cache->getCacheFolders() as $cacheFolder) {
            $path =
            $options[] = [
                'label' => $cacheFolder['shortPath'].' ('.$cacheFolder['fileCount'].')',
                'value' => $cacheFolder['path'],
            ];
        }

        return Craft::$app->getView()->renderTemplate('blitz/_utility', [
            'options' => $options,
        ]);
    }
}
