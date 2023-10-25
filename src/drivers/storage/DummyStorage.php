<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\storage;

use Craft;
use putyourlightson\blitz\models\SiteUriModel;

/**
 * A dummy storage driver, useful if pages should be cached on a reverse proxy only.
 */
class DummyStorage extends BaseCacheStorage
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'None');
    }

    public function get(SiteUriModel $siteUri): string
    {
        return '';
    }

    public function save(string $value, SiteUriModel $siteUri, int $duration = null, bool $allowEncoding = true): void
    {
    }

    public function deleteUris(array $siteUris): void
    {
    }

    public function deleteAll(): void
    {
    }
}
