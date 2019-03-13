<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\utilities;

use Craft;
use craft\base\Utility;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\purgers\DummyPurger;
use putyourlightson\blitz\records\CacheTagRecord;

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
            'tagSuggestions' => self::getTagSuggestions(),
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
            'instructions' => Craft::t('blitz', 'Flushing the cache will delete all cache records from the database.'),
        ];

        if (!(Blitz::$plugin->cachePurger->isDummy)) {
            $actions[] = [
                'id' => 'purge',
                'label' => Craft::t('blitz', 'Purge Cache'),
                'instructions' => Craft::t('blitz', 'Purging the cache will delete all cached pages in the reverse proxy purger.'),
            ];
        }

        $actions[] = [
            'id' => 'warm',
            'label' => Craft::t('blitz', 'Warm Cache'),
            'instructions' => Craft::t('blitz', 'Warming the cache will warm all of the pages.'),
        ];

        $actions[] = [
            'id' => 'refresh',
            'label' => Craft::t('blitz', 'Refresh Entire Cache'),
            'instructions' => Craft::t('blitz', 'Refreshing the cache will clear, flush, purge and warm all of the pages.'),
        ];

        $actions[] = [
            'id' => 'refresh-expired',
            'label' => Craft::t('blitz', 'Refresh Expired Cache'),
            'instructions' => Craft::t('blitz', 'Refreshing expired cache will refresh all pages and elements that have expired since they were cached.'),
        ];

        $actions[] = [
            'id' => 'refresh-tagged',
            'label' => Craft::t('blitz', 'Refresh Tagged Cache'),
            'instructions' => Craft::t('blitz', 'Refreshing tagged cache will refresh all pages that are tagged with the provided comma-separated tags.'),
        ];

        return $actions;
    }

    /**
     * Returns tag suggestions.
     *
     * @return array
     */
    public static function getTagSuggestions(): array
    {
        $tags = Blitz::$plugin->cacheTags->getAllTags();

        $data = [];

        foreach ($tags as $tag) {
            $data[] = [
                'name' => $tag,
            ];
        }

        return [[
            'label' => Craft::t('blitz', 'Tags'),
            'data' => $data,
        ]];
    }
}

