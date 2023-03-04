<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\storage;

use Craft;
use ErrorException;
use putyourlightson\blitz\events\RefreshCacheEvent;
use putyourlightson\blitz\models\SiteUriModel;
use yii\caching\CacheInterface;
use yii\db\Exception;

/**
 * @property-read string|null $settingsHtml
 */
class YiiCacheStorage extends BaseCacheStorage
{
    /**
     * @const string
     */
    public const KEY_PREFIX = 'blitz';

    /**
     * @var string
     */
    public string $cacheComponent = 'cache';

    /**
     * @var CacheInterface|null
     */
    private ?CacheInterface $_cache;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Yii Cache Storage');
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        $this->_cache = Craft::$app->get($this->cacheComponent, false);
    }

    /**
     * @inheritdoc
     */
    public function get(SiteUriModel $siteUri): string
    {
        if ($this->_cache === null) {
            return '';
        }

        $value = '';

        // Redis cache can throw an exception if the connection is broken
        try {
            $value = $this->_cache->get($this->_getKey($siteUri));

            // Decompress the value, if gzip is supported
            if (function_exists('gzdecode')) {
                // Catch E_WARNING level errors on failure
                try {
                    // Assign only if the decoded value is not `false`
                    $value = gzdecode($value) ?: $value;
                }
                /** @noinspection PhpRedundantCatchClauseInspection */
                catch (ErrorException) {
                }
            }
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (Exception) {
        }

        return $value ?: '';
    }

    /**
     * @inheritdoc
     */
    public function save(string $value, SiteUriModel $siteUri, int $duration = null): void
    {
        if ($this->_cache === null) {
            return;
        }

        // Compress the value, if gzip is supported
        if (function_exists('gzencode')) {
            $value = gzencode($value);
        }

        $this->_cache->set($this->_getKey($siteUri), $value, $duration);
    }

    /**
     * @inheritdoc
     */
    public function deleteUris(array $siteUris): void
    {
        $event = new RefreshCacheEvent(['siteUris' => $siteUris]);
        $this->trigger(self::EVENT_BEFORE_DELETE_URIS, $event);

        if (!$event->isValid) {
            return;
        }

        if ($this->_cache === null) {
            return;
        }

        foreach ($siteUris as $siteUri) {
            $this->_cache->delete($this->_getKey($siteUri));
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_URIS)) {
            $this->trigger(self::EVENT_AFTER_DELETE_URIS, $event);
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteAll(): void
    {
        $event = new RefreshCacheEvent();
        $this->trigger(self::EVENT_BEFORE_DELETE_ALL, $event);

        if (!$event->isValid) {
            return;
        }

        if ($this->_cache === null) {
            return;
        }

        $this->_cache->flush();

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_ALL)) {
            $this->trigger(self::EVENT_AFTER_DELETE_ALL, $event);
        }
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/storage/yii-cache/settings', [
            'driver' => $this,
        ]);
    }

    /**
     * Returns a key from the site URI.
     */
    private function _getKey(SiteUriModel $siteUri): array
    {
        // Cast the site ID to an integer to avoid an incorrect key
        // https://github.com/putyourlightson/craft-blitz/issues/257
        return [self::KEY_PREFIX, (int)$siteUri->siteId, $siteUri->uri];
    }
}
