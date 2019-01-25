<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers;

use Craft;
use putyourlightson\blitz\models\SiteUriModel;

/**
 *
 * @property mixed $settingsHtml
 */
class YiiCacheDriver extends BaseDriver
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

        return Craft::t('blitz', 'Yii Cache Driver [{name}]', ['name' => $name]);
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getCachedUri(SiteUriModel $siteUri): string
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
    public function saveCache(string $value, SiteUriModel $siteUri)
    {
        // Append timestamp
        $value .= '<!-- Cached by Blitz on '.date('c').' -->';

        // Force UTF8 encoding as per https://stackoverflow.com/a/9047876
        $value = "\xEF\xBB\xBF".$value;

        Craft::$app->getCache()->set([
            self::KEY_PREFIX, $siteUri->siteId, $siteUri->uri
        ], $value);
    }

    /**
     * @inheritdoc
     */
    public function clearAllCache()
    {
        Craft::$app->getCache()->flush();
    }

    /**
     * @inheritdoc
     */
    public function clearCachedUris(array $siteUris)
    {
        foreach ($siteUris as $siteUri) {
            Craft::$app->getCache()->delete([
                self::KEY_PREFIX, $siteUri->siteId, $siteUri->uri
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/yii-cache/settings', [
            'driver' => $this,
        ]);
    }
}