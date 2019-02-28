<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\utilities;

use Craft;
use craft\base\Utility;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\purgers\DummyPurger;
use putyourlightson\blitz\records\CacheFlagRecord;

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
        return Craft::$app->getView()->renderTemplate('blitz/_utility', [
            'driverHtml' => Blitz::$plugin->cacheStorage->getUtilityHtml(),
            'actions' => self::getActions(),
            'flagSuggestions' => self::getFlagSuggestions(),
        ]);
    }

    /**
     * Returns available actions.
     *
     * @return array
     */
    public static function getActions(): array
    {
        $actions = [];

        $actions[] = [
            'id' => 'clear',
            'label' => Craft::t('blitz', Craft::t('blitz', 'Clear Cache')),
            'instructions' => Craft::t('blitz', 'Clearing the cache will delete all cached pages.'),
        ];

        $actions[] = [
            'id' => 'flush',
            'label' => Craft::t('blitz', 'Flush Cache'),
            'instructions' => Craft::t('blitz', 'Flushing the cache will clear the cache and remove all records from the database.'),
        ];

        if (!(Blitz::$plugin->cachePurger instanceof DummyPurger)) {
            $actions[] = [
                'id' => 'purge',
                'label' => Craft::t('blitz', 'Purge Cache'),
                'instructions' => Craft::t('blitz', 'Purging the cache will delete all cached pages in the reverse proxy purger.'),
            ];
        }

        $actions[] = [
            'id' => 'warm',
            'label' => Craft::t('blitz', 'Warm Cache'),
            'instructions' => Craft::t('blitz', 'Warming the cache will flush the cache and add a job to the queue to recache all of the pages.'),
        ];

        $actions[] = [
            'id' => 'refresh-expired',
            'label' => Craft::t('blitz', 'Refresh Expired Cache'),
            'instructions' => Craft::t('blitz', 'Refreshing expired cache will refresh all elements that have expired since they were cached.'),
        ];

        $actions[] = [
            'id' => 'refresh-flagged',
            'label' => Craft::t('blitz', 'Refresh Flagged Cache'),
            'instructions' => Craft::t('blitz', 'Refreshing flagged cache will refresh all pages that are associated with the given flags (separated by commas).'),
        ];

        return $actions;
    }

    /**
     * Returns flag suggestions.
     *
     * @return array
     */
    public static function getFlagSuggestions(): array
    {
        $flags = CacheFlagRecord::find()
            ->select('flag')
            ->groupBy('flag')
            ->column();

        $data = [];

        foreach ($flags as $flag) {
            $data[] = [
                'name' => $flag,
            ];
        }

        return [[
            'label' => Craft::t('blitz', 'Flags'),
            'data' => $data,
        ]];
    }
}

