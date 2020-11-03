<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit\drivers;

use Codeception\Test\Unit;
use Craft;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\storage\YiiCacheStorage;
use putyourlightson\blitz\models\SiteUriModel;
use UnitTester;
use yii\redis\Cache;
use yii\redis\Connection;

/**
 * @author    PutYourLightsOn
 * @package   Blitz
 * @since     3.6.9
 */

class RedisStorageTest extends Unit
{
    // Properties
    // =========================================================================

    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var YiiCacheStorage
     */
    private $cacheStorage;

    /**
     * @var SiteUriModel
     */
    private $siteUri;

    /**
     * @var string
     */
    private $output = 'xyz';

    // Protected methods
    // =========================================================================

    protected function _before()
    {
        parent::_before();

        // Set cache component to Redis
        Craft::$app->set('redis', [
            'class' => Connection::class,
            'hostname' => 'localhost',
            'port' => 6379,
        ]);
        Craft::$app->set('cache', [
            'class' => Cache::class,
            'defaultDuration' => 86400,
            'keyPrefix' => 'CraftCMS',
        ]);

        // Set cache storage to YiiCacheStorage
        Blitz::$plugin->set('cacheStorage', YiiCacheStorage::class);

        Blitz::$plugin->generateCache->options->cachingEnabled = true;
        Blitz::$plugin->cacheStorage->deleteAll();
        Blitz::$plugin->flushCache->flushAll();

        $this->cacheStorage = Blitz::$plugin->cacheStorage;

        $this->siteUri = new SiteUriModel([
            'siteId' => 1,
            'uri' => 'möbelträgerfüße',
        ]);

        Blitz::$plugin->cacheStorage->save($this->output, $this->siteUri);
    }

    // Public methods
    // =========================================================================

    public function testSave()
    {
        $value = $this->cacheStorage->get($this->siteUri);
        $this->assertStringContainsString($this->output, $value);
    }

    public function testSaveDecoded()
    {
        $this->siteUri->uri = rawurldecode($this->siteUri->uri);
        $value = $this->cacheStorage->get($this->siteUri);
        $this->assertStringContainsString($this->output, $value);
    }

    public function testDelete()
    {
        $this->cacheStorage->deleteUris([$this->siteUri]);
        $value = $this->cacheStorage->get($this->siteUri);
        $this->assertEmpty($value);
    }

    public function testDeleteDecoded()
    {
        $this->siteUri->uri = rawurldecode($this->siteUri->uri);
        $this->cacheStorage->deleteUris([$this->siteUri]);
        $value = $this->cacheStorage->get($this->siteUri);
        $this->assertEmpty($value);
    }
}
