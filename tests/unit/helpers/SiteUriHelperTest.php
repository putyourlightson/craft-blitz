<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit\helpers;

use Codeception\Test\Unit;
use Craft;
use craft\elements\Asset;
use craft\models\Site;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitztests\fixtures\AssetFixture;
use UnitTester;

/**
 * @since 3.9.0
 */
class SiteUriHelperTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var Site|null
     */
    private ?Site $secondarySite = null;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'assets' => [
                'class' => AssetFixture::class,
            ],
        ];
    }

    protected function _before()
    {
        parent::_before();

        if ($this->secondarySite === null) {
            $primarySite = Craft::$app->sites->getPrimarySite();
            $this->secondarySite = new Site([
                'groupId' => $primarySite->groupId,
                'name' => 'Secondary',
                'handle' => 'secondary',
                'language' => $primarySite->language,
                'baseUrl' => trim($primarySite->baseUrl, '/') . '/secondary/',
            ]);

            Craft::$app->sites->saveSite($this->secondarySite);
        }
    }

    public function testGetSiteUrisFromAssetWithTransform()
    {
        $asset = Asset::find()->one();

        // A named transform won't work here, as all transforms are fetched on init
        $asset->getUrl(['width' => 30, 'height' => 30], true);

        $siteUris = SiteUriHelper::getAssetSiteUris([$asset->id]);
        $this->assertEquals([
                new SiteUriModel([
                    'siteId' => $asset->siteId,
                    'uri' => 'test-volume-1/' . $asset->filename,
                ]),
                new SiteUriModel([
                    'siteId' => $asset->siteId,
                    'uri' => 'test-volume-1/_30x30_crop_center-center_none/' . $asset->filename,
                ]),
            ],
            $siteUris,
        );
    }

    public function testGetSiteUrisForSiteWithQueryString()
    {
        Blitz::$plugin->settings->includedUriPatterns = [
            'siteId' => 1,
            'uriPattern' => '.*',
        ];
        Blitz::$plugin->settings->queryStringCaching = SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES;

        $siteUri = new SiteUriModel([
            'siteId' => 1,
            'uri' => 'abc?x=3',
        ]);

        $record = new CacheRecord($siteUri->toArray());
        $record->save();

        $siteUris = SiteUriHelper::getSiteUrisForSite(1, true);
        $this->assertEquals([$siteUri], $siteUris);

        Blitz::$plugin->settings->generatePagesWithQueryStringParams =false;
        $siteUris = SiteUriHelper::getSiteUrisForSite(1, true);
        $this->assertEquals([], $siteUris);
    }

    public function testGetMimeType()
    {
        $siteUri = new SiteUriModel(['siteId' => 1]);

        $siteUri->uri = 'xyz.txt';
        $this->assertEquals('text/plain', SiteUriHelper::getMimeType($siteUri));

        $siteUri->uri = 'xyz?test.txt';
        $this->assertTrue(SiteUriHelper::hasHtmlMimeType($siteUri));
    }

    public function testIsPaginatedUri()
    {
        $this->assertFalse(SiteUriHelper::isPaginatedUri('xyz'));

        Craft::$app->config->general->pageTrigger = 'page';
        $this->assertTrue(SiteUriHelper::isPaginatedUri('xyz/page4'));

        Craft::$app->config->general->pageTrigger = 'page/';
        $this->assertTrue(SiteUriHelper::isPaginatedUri('xyz/page/4'));

        Craft::$app->config->general->pageTrigger = '?page=';
        $this->assertTrue(SiteUriHelper::isPaginatedUri('xyz?t=1&page=4'));
    }
}
