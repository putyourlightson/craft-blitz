<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit\drivers;

use Codeception\Test\Unit;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\storage\CacheStorageInterface;
use putyourlightson\blitz\drivers\storage\FileStorage;
use putyourlightson\blitz\models\SiteUriModel;
use UnitTester;

/**
 * @since 3.6.9
 */

class FileStorageTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var FileStorage
     */
    private CacheStorageInterface $cacheStorage;

    /**
     * @var SiteUriModel
     */
    private SiteUriModel $siteUri;

    /**
     * @var string
     */
    private string $output = 'xyz';

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
        $total = 3;
        $siteUri = new SiteUriModel([
            'siteId' => 1,
            'uri' => $path . '/test-with-äöü-',
        ]);

        for ($i = 0; $i < $total; $i++) {
            $siteUri->uri .= $i;
            $this->cacheStorage->save('test', $siteUri);
        }

        $this->assertEquals($total, $this->cacheStorage->getCachedPageCount($path));
    }

    public function testGetFilePathsWithQueryStrings()
    {
        $siteUri = new SiteUriModel([
            'siteId' => 1,
            'uri' => 'test?q=x%2Cy',
        ]);

        $filePath = $this->cacheStorage->getFilePaths($siteUri)[0] ?? '';
        $this->assertStringContainsString('test/q=x%2C', $filePath);

        $siteUri->uri = 'test?q=x%2C/..';
        $this->assertEquals([], $this->cacheStorage->getFilePaths($siteUri));
    }
}
