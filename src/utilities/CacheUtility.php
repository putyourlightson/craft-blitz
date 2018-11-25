<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\FileHelper;
use putyourlightson\blitz\Blitz;

class CacheUtility extends Utility
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Blitz Cache');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'blitz-cache';
    }

    /**
     * @inheritdoc
     */
    public static function iconPath()
    {
        return Craft::getAlias('@vendor/putyourlightson/craft-blitz/src/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $options = [];

        $cacheFolderPath = Blitz::$plugin->file->getCacheFolderPath();

        if ($cacheFolderPath && is_dir($cacheFolderPath)) {
            $cacheFolders = [];

            foreach (FileHelper::findDirectories($cacheFolderPath) as $cacheFolder) {
                $count = count(FileHelper::findFiles($cacheFolder));
                $options[] = [
                    'label' => trim(str_replace(Craft::getAlias('@webroot'), '', $cacheFolder), '/').' ('.$count.' file'.($count == 1 ? '' : 's').')',
                    'value' => $cacheFolder,
                ];
            }
        }

        return Craft::$app->getView()->renderTemplate('blitz/_utility', [
            'options' => $options,
        ]);
    }
}
