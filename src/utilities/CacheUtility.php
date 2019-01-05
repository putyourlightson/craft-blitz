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
        $sites = [];
        $sitePaths = [];

        $allSites = Craft::$app->getSites()->getAllSites();

        foreach ($allSites as $site) {
            $path = Blitz::$plugin->file->getSitePath($site->id);
            $count = is_dir($path) ? count(FileHelper::findFiles($path)) : 0;

            $relativePath = trim(str_replace(Craft::getAlias('@webroot'), '', $path), '/');

            $sites[$site->id] = [
                'name' => $site->name,
                'path' => $path,
                'relativePath' => $relativePath,
                'count' => $count,
            ];
        }

        foreach ($sites as $id => $site) {
            // Count subpaths so we can get an accurate count for this site's path only
            $subpathCount = 0;

            foreach ($sites as $otherId => $otherSite) {
                if ($otherId != $id && strpos($otherSite['path'], $site['path']) === 0) {
                    $subpathCount += is_dir($otherSite['path']) ? count(FileHelper::findFiles($otherSite['path'])) : 0;
                }
            }

            $sites[$id]['count'] = $sites[$id]['count'] - $subpathCount;
        }

        return Craft::$app->getView()->renderTemplate('blitz/_utility', [
            'sites' => $sites,
        ]);
    }
}
