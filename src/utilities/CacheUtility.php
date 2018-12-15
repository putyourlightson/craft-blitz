<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\FileHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\records\CacheRecord;

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

        $sites = Craft::$app->getSites()->getAllSites();

        foreach ($sites as $site) {
            $cacheRecordCount = CacheRecord::find()
                ->where(['siteId' => $site->id])
                ->count();

            $options[] = [
                'label' => Craft::t('blitz', '{site} ({count} {n,plural,=1{page} other{pages}} cached)',
                    ['site' => $site->name, 'count' => $cacheRecordCount, 'n' => $cacheRecordCount]
                ),
                'value' => $site->id,
            ];
        }

        return Craft::$app->getView()->renderTemplate('blitz/_utility', [
            'options' => $options,
        ]);
    }
}
