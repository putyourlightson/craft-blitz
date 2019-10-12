<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\utilities;

use Craft;
use craft\base\Utility;
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
        return Craft::$app->getView()->renderTemplate('blitz/_utility', [
            'driverHtml' => Blitz::$plugin->cacheStorage->getUtilityHtml(),
            'actions' => self::getActions(),
            'tagSuggestions' => self::getTagSuggestions(),
        ]);
    }

    /**
     * Returns available actions.
     *
     * @param bool $showAll
     *
     * @return array
     */
    public static function getActions(bool $showAll = false): array
    {
        $actions = [];

        $actions[] = [
            'id' => 'clear',
            'label' => Craft::t('blitz', Craft::t('blitz', 'Clear Cache')),
            'instructions' => Craft::t('blitz', 'Deletes all cached pages.'),
        ];

        $actions[] = [
            'id' => 'flush',
            'label' => Craft::t('blitz', 'Flush Cache'),
            'instructions' => Craft::t('blitz', 'Deletes all cache records from the database.'),
        ];

        if ($showAll || !Blitz::$plugin->cachePurger->isDummy) {
            $actions[] = [
                'id' => 'purge',
                'label' => Craft::t('blitz', 'Purge Cache'),
                'instructions' => Craft::t('blitz', 'Deletes all cached pages in the reverse proxy.'),
            ];
        }

        $actions[] = [
            'id' => 'warm',
            'label' => Craft::t('blitz', 'Warm Cache'),
            'instructions' => Craft::t('blitz', 'Warms all of the cacheable pages.'),
        ];

        if ($showAll || !Blitz::$plugin->deployer->isDummy) {
            $actions[] = [
                'id' => 'deploy',
                'label' => Craft::t('blitz', 'Deploy to Remote'),
                'instructions' => Craft::t('blitz', 'Deploys all cached files to the remote location.'),
            ];
        }

        $actions[] = [
            'id' => 'refresh',
            'label' => Craft::t('blitz', 'Refresh Cache'),
            'instructions' => Craft::t('blitz', 'Refreshes (clears, purges, flushes, warms, deploys) all of the pages.'),
        ];

        $actions[] = [
            'id' => 'refresh-expired',
            'label' => Craft::t('blitz', 'Refresh Expired Cache'),
            'instructions' => Craft::t('blitz', 'Refreshes pages that have expired since they were cached.'),
        ];

        $actions[] = [
            'id' => 'refresh-urls',
            'label' => Craft::t('blitz', 'Refresh Cached URLs'),
            'instructions' => Craft::t('blitz', 'Refreshes pages with the provided URLs.'),
        ];

        $actions[] = [
            'id' => 'refresh-tagged',
            'label' => Craft::t('blitz', 'Refresh Tagged Cache'),
            'instructions' => Craft::t('blitz', 'Refreshes pages with the provided tags.'),
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

