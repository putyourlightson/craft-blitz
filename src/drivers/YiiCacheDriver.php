<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers;

use Craft;

class YiiCacheDriver extends BaseDriver
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $name = get_class(Craft::$app->getCache());

        return Craft::t('blitz', 'Yii Cache Driver ({name})', ['name' => $name]);
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getCachedUri(int $siteId, string $uri): string
    {
        $value = Craft::$app->getCache()->get([$siteId, $uri]);

        if ($value === false) {
            return '';
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function getCacheCount(int $siteId): int
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public function saveCache(string $value, int $siteId, string $uri)
    {
        // Append timestamp
        $value .= '<!-- Cached by Blitz on '.date('c').' -->';

        // Force UTF8 encoding as per https://stackoverflow.com/a/9047876
        $value = "\xEF\xBB\xBF".$value;

        Craft::$app->getCache()->set([$siteId, $uri], $value);
    }

    /**
     * @inheritdoc
     */
    public function clearCache()
    {
        Craft::$app->getCache()->flush();
    }

    /**
     * @inheritdoc
     */
    public function clearCachedUri(int $siteId, string $uri)
    {
        Craft::$app->getCache()->delete([$siteId, $uri]);
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