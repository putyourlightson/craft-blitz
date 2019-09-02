<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\storage;

use Craft;
use putyourlightson\blitz\models\SiteUriModel;
use yii\caching\CacheInterface;

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

    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public $cacheComponent = 'cache';

    /**
     * @var CacheInterface|null
     */
    private $_cache;

    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Yii Cache Storage');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
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

        $value = $this->_cache->get([
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
        if ($this->_cache === null) {
            return;
        }

        $this->_cache->set([
            self::KEY_PREFIX, $siteUri->siteId, $siteUri->uri
        ], $value);
    }

    /**
     * @inheritdoc
     */
    public function delete(SiteUriModel $siteUri)
    {
        if ($this->_cache === null) {
            return;
        }

        $this->_cache->delete([
            self::KEY_PREFIX, $siteUri->siteId, $siteUri->uri
        ]);
    }

    /**
     * @inheritdoc
     */
    public function deleteAll()
    {
        if ($this->_cache === null) {
            return;
        }

        $this->_cache->flush();
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
