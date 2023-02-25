<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit\helpers;

use Codeception\Test\Unit;
use Craft;
use craft\elements\Asset;
use craft\models\Site;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;
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

    public function testGetMimeType()
    {
        $siteUri = new SiteUriModel(['siteId' => 1]);

        $siteUri->uri = 'xyz.txt';
        $this->assertEquals('text/plain', SiteUriHelper::getMimeType($siteUri));

        $siteUri->uri = 'xyz?test.txt';
        $this->assertTrue(SiteUriHelper::hasHtmlMimeType($siteUri));
    }

    public function testGetSiteUrisFromAsset()
    {
        $asset = Asset::find()->one();
        $siteUris = SiteUriHelper::getAssetSiteUris([$asset->id]);
        $this->assertCount(1, $siteUris);
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
