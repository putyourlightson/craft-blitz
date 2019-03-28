<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\storage;

use Craft;
use putyourlightson\blitz\models\SiteUriModel;

/**
 *
 * @property mixed $settingsHtml
 */
class YiiCacheStorage extends BaseCacheStorage
{
    // Constants
    // =========================================================================

    /**
     * @const string
     */
    const KEY_PREFIX = 'blitz';

    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $name = get_class(Craft::$app->getCache());

        return Craft::t('blitz', 'Yii Cache Storage [{name}]', ['name' => $name]);
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function get(SiteUriModel $siteUri): string
    {
        $value = Craft::$app->getCache()->get([
            self::KEY_PREFIX, $siteUri->siteId, $siteUri->uri
        ]);

        if ($value === false) {
            return '';
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function save(string $value, SiteUriModel $siteUri)
    {
        Craft::$app->getCache()->set([
            self::KEY_PREFIX, $siteUri->siteId, $siteUri->uri
        ], $value);
    }

    /**
     * @inheritdoc
     */
    public function delete(SiteUriModel $siteUri)
    {
        Craft::$app->getCache()->delete([
            self::KEY_PREFIX, $siteUri->siteId, $siteUri->uri
        ]);
    }

    /**
     * @inheritdoc
     */
    public function deleteAll()
    {
        Craft::$app->getCache()->flush();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/storage/yii-cache/settings', [
            'driver' => $this,
        ]);
    }
}