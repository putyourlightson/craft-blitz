<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\base\Element;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\Db;
use DateInterval;
use DateTime;
use putyourlightson\blitz\behaviors\ElementChangedBehavior;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\FieldHelper;
use putyourlightson\blitz\jobs\RefreshCacheJob;
use putyourlightson\blitz\models\RefreshDataModel;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\ElementExpiryDateRecord;
use putyourlightson\blitztests\fixtures\AssetFixture;
use putyourlightson\blitztests\fixtures\EntryFixture;
use UnitTester;

/**
 * @since 3.1.0
 */
class RefreshCacheTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var SiteUriModel
     */
    private SiteUriModel $siteUri;

    /**
     * @var string
     */
    private string $output = 'xyz';

    /**
     * @var Entry|ElementChangedBehavior
     */
    private Entry|ElementChangedBehavior $entry;

    /**
     * @var Asset|ElementChangedBehavior
     */
    private Asset|ElementChangedBehavior $asset;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'entries' => [
                'class' => EntryFixture::class,
            ],
            'assets' => [
                'class' => AssetFixture::class,
            ],
        ];
    }

    protected function _before()
    {
        parent::_before();

        Blitz::$plugin->generateCache->options->cachingEnabled = true;

        Blitz::$plugin->cacheStorage->deleteAll();
        Blitz::$plugin->flushCache->flushAll();

        $this->siteUri = new SiteUriModel([
            'siteId' => 1,
            'uri' => 'page',
        ]);

        $this->entry = Entry::find()->sectionId(1)->one();
        $this->entry->attachBehavior(ElementChangedBehavior::BEHAVIOR_NAME, ElementChangedBehavior::class);

        $this->asset = Asset::find()->one();
        $this->asset->setFocalPoint([
            'x' => 0.1,
            'y' => 0.1,
        ]);
        Craft::$app->elements->saveElement($this->asset);
        $this->asset->attachBehavior(ElementChangedBehavior::BEHAVIOR_NAME, ElementChangedBehavior::class);

        Blitz::$plugin->refreshCache->reset();
        Blitz::$plugin->refreshCache->batchMode = true;
    }

    public function testAddElementWhenUnchanged()
    {
        Blitz::$plugin->refreshCache->addElement($this->entry);

        // Assert that the refresh data is empty
        $this->assertTrue(Blitz::$plugin->refreshCache->refreshData->isEmpty());
    }

    public function testAddElementWhenAttributeChanged()
    {
        $this->entry->title .= ' X';
        Blitz::$plugin->refreshCache->addElement($this->entry);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry, ['title']);
    }

    public function testAddElementWhenFieldChanged()
    {
        $this->entry->setFieldValue('text', '123');
        Blitz::$plugin->refreshCache->addElement($this->entry);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry, [], ['text']);
    }

    public function testAddElementMultipleTimesWhenAttributesChanged()
    {
        $this->entry->title .= ' X';
        Blitz::$plugin->refreshCache->addElement($this->entry);

        $this->entry->setFieldValue('text', '123');
        Blitz::$plugin->refreshCache->addElement($this->entry);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry, ['title'], ['text']);
    }

    public function testAddElementMultipleTimesWhenFieldsChanged()
    {
        $this->entry->setFieldValue('text', '123');
        Blitz::$plugin->refreshCache->addElement($this->entry);

        $this->entry->setFieldValue('moreText', '123');
        Blitz::$plugin->refreshCache->addElement($this->entry);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry, [], ['text', 'moreText']);
    }

    public function testAddElementWhenAttributeAndFieldChanged()
    {
        $this->entry->title .= ' X';
        $this->entry->setFieldValue('text', '123');
        Blitz::$plugin->refreshCache->addElement($this->entry);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry, ['title'], ['text']);
    }

    public function testAddElementWhenFocalPointChanged()
    {
        $this->asset->setFocalPoint([
            'x' => 101,
            'y' => 101,
        ]);
        Blitz::$plugin->refreshCache->addElement($this->asset);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->asset);

        $this->assertEquals(
            [$this->asset->id],
            Blitz::$plugin->refreshCache->refreshData->getAssetsChangedByImage(),
        );
    }

    public function testAddElementWhenStatusChanged()
    {
        $this->entry->originalElement->enabled = false;
        $this->entry->enabled = false;
        Blitz::$plugin->refreshCache->addElement($this->entry);

        // Assert that the refresh data is empty
        $this->assertTrue(Blitz::$plugin->refreshCache->refreshData->isEmpty());

        $this->entry->originalElement->enabled = true;
        Blitz::$plugin->refreshCache->addElement($this->entry);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry);
    }

    public function testAddElementWhenExpired()
    {
        // Set the expiryData in the past
        $this->entry->expiryDate = new DateTime('20010101');
        Blitz::$plugin->refreshCache->addElement($this->entry);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry);
    }

    public function testAddElementWhenDeleted()
    {
        // Delete the element
        Craft::$app->getElements()->deleteElement($this->entry);

        // Assert that the tracked element is correct
        $this->_assertTrackedElement($this->entry);
    }

    public function testAddElementExpiryDates()
    {
        $this->entry->expiryDate = (new DateTime('now'))->add(new DateInterval('P2D'));

        Blitz::$plugin->refreshCache->addElementExpiryDates($this->entry);

        /** @var ElementExpiryDateRecord $elementExpiryDateRecord */
        $elementExpiryDateRecord = ElementExpiryDateRecord::find()
            ->where(['elementId' => $this->entry->id])
            ->one();

        // Assert that the expiry date is correct
        $this->assertEquals(
            Db::prepareDateForDb($this->entry->expiryDate),
            $elementExpiryDateRecord->expiryDate
        );

        $this->entry->postDate = (new DateTime('now'))->add(new DateInterval('P1D'));

        Blitz::$plugin->refreshCache->addElementExpiryDates($this->entry);

        /** @var ElementExpiryDateRecord $elementExpiryDateRecord */
        $elementExpiryDateRecord = ElementExpiryDateRecord::find()
            ->where(['elementId' => $this->entry->id])
            ->one();

        // Assert that the expiry date is correct
        $this->assertEquals(
            Db::prepareDateForDb($this->entry->postDate),
            $elementExpiryDateRecord->expiryDate
        );
    }

    public function testRefreshElementQuery()
    {
        // Add element query and save
        $elementQuery = Entry::find()->sectionId($this->entry->sectionId);
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        // Assert that the output (which may also contain a timestamp) contains the cached value
        $this->assertStringContainsString($this->output, Blitz::$plugin->cacheStorage->get($this->siteUri));

        $refreshData = RefreshDataModel::createFromElement($this->entry);
        $refreshCacheJob = new RefreshCacheJob([
            'data' => $refreshData->data,
            'forceClear' => true,
        ]);
        $refreshCacheJob->execute(Craft::$app->getQueue());

        // Assert that the cached value is a blank string
        $this->assertEquals('', Blitz::$plugin->cacheStorage->get($this->siteUri));
    }

    public function testRefreshSourceTag()
    {
        // Add source tag and save
        Blitz::$plugin->generateCache->options->tags('sectionId:' . $this->entry->sectionId);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        // Assert that the output (which may also contain a timestamp) contains the cached value
        $this->assertStringContainsString($this->output, Blitz::$plugin->cacheStorage->get($this->siteUri));

        $refreshData = new RefreshDataModel();
        $refreshData->addSourceId($this->entry::class, $this->entry->sectionId);
        $refreshCacheJob = new RefreshCacheJob([
            'data' => $refreshData->data,
            'forceClear' => true,
        ]);
        $refreshCacheJob->execute(Craft::$app->getQueue());

        // Assert that the cached value is a blank string
        $this->assertEquals('', Blitz::$plugin->cacheStorage->get($this->siteUri));
    }

    public function testRefreshCacheTag()
    {
        // Add tag and save
        $tag = 'abc';
        Blitz::$plugin->generateCache->options->tags($tag);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        // Assert that the output (which may also contain a timestamp) contains the cached value
        $this->assertStringContainsString($this->output, Blitz::$plugin->cacheStorage->get($this->siteUri));

        Blitz::$plugin->refreshCache->refreshCacheTags([$tag]);

        Craft::$app->runAction('queue/run');

        // Assert that the cached value is a blank string
        $this->assertEquals('', Blitz::$plugin->cacheStorage->get($this->siteUri));
    }

    public function testRefreshCachedUrls()
    {
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        // Assert that the output (which may also contain a timestamp) contains the cached value
        $this->assertStringContainsString($this->output, Blitz::$plugin->cacheStorage->get($this->siteUri));

        Blitz::$plugin->refreshCache->refreshCachedUrls([$this->siteUri->url]);

        // Assert that the cached value is a blank string
        $this->assertEquals('', Blitz::$plugin->cacheStorage->get($this->siteUri));
    }

    public function testRefreshCachedUrlsWithWildcard()
    {
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        // Assert that the output (which may also contain a timestamp) contains the cached value
        $this->assertStringContainsString($this->output, Blitz::$plugin->cacheStorage->get($this->siteUri));

        $url = substr($this->siteUri->url, 0, -1) . '*';
        Blitz::$plugin->refreshCache->refreshCachedUrls([$url]);

        // Assert that the cached value is a blank string
        $this->assertEquals('', Blitz::$plugin->cacheStorage->get($this->siteUri));
    }

    private function _assertTrackedElement(Element|ElementChangedBehavior $element, array $changedAttributes = [], array $changedFields = [])
    {
        $refreshData = Blitz::$plugin->refreshCache->refreshData;

        $this->assertEquals(
            !empty($element->sectionId) ? [$element->sectionId] : [],
            $refreshData->getSourceIds($element::class),
        );

        $this->assertEquals(
            $changedAttributes,
            $refreshData->getChangedAttributes($element::class, $element->id),
        );

        $changedFields = FieldHelper::getFieldIdsFromHandles($changedFields);

        $this->assertEquals(
            $changedFields,
            $refreshData->getChangedFields($element::class, $element->id),
        );
    }
}
