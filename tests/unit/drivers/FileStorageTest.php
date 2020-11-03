<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit\drivers;

use Codeception\Test\Unit;
use craft\helpers\FileHelper;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\storage\FileStorage;
use putyourlightson\blitz\models\SiteUriModel;
use UnitTester;

/**
 * @author    PutYourLightsOn
 * @package   Blitz
 * @since     3.6.9
 */

class FileStorageTest extends Unit
{
    // Properties
    // =========================================================================

    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var FileStorage
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

        // Set cache storage to FileStorage
        Blitz::$plugin->set('cacheStorage', FileStorage::class);

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

    public function testGetCachedFileCount()
    {
        $this->cacheStorage->deleteAll();

        $path = $this->cacheStorage->getSitePath(1);
        $total = 10;

        for ($i = 0; $i < $total; $i++) {
            FileHelper::writeToFile($path.'/test-'.$i.'/index.html', 'test');
        }

        $this->assertEquals($total, $this->cacheStorage->getCachedFileCount($path));
    }
}
