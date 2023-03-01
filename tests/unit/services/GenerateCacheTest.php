<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Product;
use craft\db\FixedOrderExpression;
use craft\elements\Entry;
use craft\elements\User;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\FieldHelper;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementFieldCacheRecord;
use putyourlightson\blitz\records\ElementQueryAttributeRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryFieldRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;
use putyourlightson\blitz\records\IncludeRecord;
use putyourlightson\blitz\records\SsiIncludeCacheRecord;
use putyourlightson\blitztests\fixtures\EntryFixture;
use putyourlightson\campaign\elements\CampaignElement;
use putyourlightson\campaign\elements\MailingListElement;
use UnitTester;

/**
 * @since 2.3.0
 */

class GenerateCacheTest extends Unit
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
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'entries' => [
                'class' => EntryFixture::class,
            ],
        ];
    }

    protected function _before()
    {
        parent::_before();

        Blitz::$plugin->settings->cachingEnabled = true;
        Blitz::$plugin->cacheStorage->deleteAll();
        Blitz::$plugin->flushCache->flushAll();

        $this->siteUri = new SiteUriModel([
            'siteId' => 1,
            'uri' => 'page',
        ]);
    }

    public function testSaveCache()
    {
        // Save the output for the site URI
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);
        $value = Blitz::$plugin->cacheStorage->get($this->siteUri);

        // Assert that the output contains the cached value
        $this->assertStringContainsString($this->output, $value);

        // Assert that the output contains a timestamp
        $this->assertStringContainsString('Cached by Blitz on', $value);
    }

    public function testSaveCacheWithOutputComments()
    {
        Blitz::$plugin->generateCache->options->outputComments = false;
        $value = Blitz::$plugin->generateCache->save($this->output, $this->siteUri);
        $this->assertEquals($this->output, $value);

        Blitz::$plugin->generateCache->options->outputComments = true;
        $value = Blitz::$plugin->generateCache->save($this->output, $this->siteUri);
        $this->assertStringContainsString('Cached by Blitz on', $value);

        Blitz::$plugin->generateCache->options->outputComments = SettingsModel::OUTPUT_COMMENTS_SERVED;
        $value = Blitz::$plugin->generateCache->save($this->output, $this->siteUri);
        $this->assertEquals($this->output, $value);

        Blitz::$plugin->generateCache->options->outputComments = SettingsModel::OUTPUT_COMMENTS_CACHED;
        $value = Blitz::$plugin->generateCache->save($this->output, $this->siteUri);
        $this->assertStringContainsString('Cached by Blitz on', $value);
    }

    public function testSaveCacheWithFileExtension()
    {
        $siteUri = new SiteUriModel(['siteId' => $this->siteUri->siteId]);
        $siteUri->uri = $this->siteUri->uri . '.html';

        $value = Blitz::$plugin->generateCache->save($this->output, $siteUri);

        // Assert that the output contains a timestamp
        $this->assertStringContainsString('Cached by Blitz on', $value);

        $siteUri->uri = $this->siteUri->uri . '.json';

        $value = Blitz::$plugin->generateCache->save($this->output, $siteUri);

        // Assert that the output does not contain a timestamp
        $this->assertStringNotContainsString('Cached by Blitz on', $value);
    }

    public function testSaveCacheRecord()
    {
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);
        $count = CacheRecord::find()
            ->where($this->siteUri->toArray())
            ->count();

        // Assert that the record was saved
        $this->assertEquals(1, $count);
    }

    public function testSaveElementCacheRecord()
    {
        $element = User::find()->one();
        Blitz::$plugin->generateCache->addElement($element);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        /** @var ElementCacheRecord $record */
        $record = ElementCacheRecord::find()
            ->where(['elementId' => $element->id])
            ->one();

        $this->assertEquals($element->id, $record->elementId);
    }

    public function testSaveElementCacheRecordWithoutCustomFields()
    {
        $element = User::find()->one();
        Blitz::$plugin->generateCache->addElement($element);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $count = ElementCacheRecord::find()
            ->where(['elementId' => $element->id])
            ->count();

        $this->assertEquals(1, $count);
    }

    public function testSaveElementCacheRecordWithCustomFields()
    {
        $element = Entry::find()->one();
        Blitz::$plugin->generateCache->addElement($element);
        /** @noinspection PhpUnusedLocalVariableInspection */
        $text = $element->text;
        /** @noinspection PhpUnusedLocalVariableInspection */
        $moreText = $element->moreText;
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $count = ElementCacheRecord::find()
            ->where(['elementId' => $element->id])
            ->count();

        $this->assertEquals(1, $count);

        $count = ElementFieldCacheRecord::find()
            ->where(['elementId' => $element->id])
            ->count();

        $this->assertEquals(2, $count);
    }

    public function testSaveElementQueryRecords()
    {
        $elementQueries = [
            Entry::find()->id(1),
            Entry::find()->id('1'),
            Entry::find()->id('1, 2, 3'),
            Entry::find()->id([1, 2, 3]),
            Entry::find()->id(['1', '2', '3']),
            Entry::find()->slug('slug'),
            Entry::find()->slug(['slug']),
            Entry::find()->slug([null, 'slug']),
            Entry::find()->orderBy('RAND()'),
            Entry::find()->orderBy('Rand(123)'),
        ];

        foreach ($elementQueries as $elementQuery) {
            Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        }

        $count = ElementQueryRecord::find()->count();

        // Assert that no records were saved
        $this->assertEquals(0, $count);

        $elementQuerySets = [
            [
                Entry::find(),
                Entry::find()->limit(''),
                Entry::find()->offset(0),
            ],
            [
                Entry::find()->id('not 1'),
            ],
            [
                Entry::find()->id(['not', 1]),
                Entry::find()->id(['not', '1']),
            ],
            [
                Entry::find()->slug('not slug'),
            ],
            [
                Entry::find()->slug(['not', 'slug']),
            ],
            [
                Entry::find()->sectionId(1),
                Entry::find()->sectionId('1'),
                Entry::find()->sectionId([1]),
                Entry::find()->sectionId(['1']),
            ],
            [
                Entry::find()->sectionId('1, 2'),
                Entry::find()->sectionId([1, 2]),
            ],
        ];

        foreach ($elementQuerySets as $elementQuerySet) {
            foreach ($elementQuerySet as $elementQuery) {
                Blitz::$plugin->generateCache->addElementQuery($elementQuery);
            }
        }

        $count = ElementQueryRecord::find()->count();

        // Assert that all records were saved
        $this->assertEquals(count($elementQuerySets), $count);
    }

    public function testSaveElementQueryWithJoin()
    {
        $elementQuery = Entry::find()->innerJoin('{{%users}}');
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);

        $this->assertEquals(1, ElementQueryRecord::find()->count());
    }

    public function testSaveElementQueryWithRelationFields()
    {
        $element = Entry::find()->one();
        $elementQuery = $element->relatedEntries;
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);

        $this->assertEquals(0, ElementQueryRecord::find()->count());
    }

    public function testSaveElementQueryWithRelatedToParam()
    {
        $elementQuery = Entry::find()->relatedTo(1);
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);

        $this->assertEquals(1, ElementQueryRecord::find()->count());
    }

    public function testSaveElementQueryWithExpression()
    {
        $expression = new FixedOrderExpression('elements.id', [], Craft::$app->db);
        $elementQuery = Entry::find()->orderBy($expression);
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);

        $this->assertEquals(0, ElementQueryRecord::find()->count());
    }

    public function testSaveElementQueryCacheRecords()
    {
        $elementQuery = Entry::find();

        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        Blitz::$plugin->generateCache->save($this->output, new SiteUriModel([
            'siteId' => 1,
            'uri' => 'new',
        ]));

        $count = ElementQueryCacheRecord::find()->count();

        // Assert that two records were saved
        $this->assertEquals(2, $count);
    }

    public function testSaveElementQuerySourceRecords()
    {
        $elementQueries = [
            Entry::find()->sectionId('not 1'),
            Entry::find()->sectionId('> 1'),
            Entry::find()->sectionId(['not', 1]),
            Entry::find()->sectionId(['not', '1']),
            Entry::find()->sectionId(['>', '1']),
        ];

        foreach ($elementQueries as $elementQuery) {
            Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        }

        $count = ElementQuerySourceRecord::find()->count();

        // Assert that no records were saved
        $this->assertEquals(0, $count);

        $elementQueries = [
            Entry::find(),
            Entry::find()->sectionId(1),
            Entry::find()->sectionId([1, 2, 3]),
            Product::find()->typeId(4),
            CampaignElement::find()->campaignTypeId(5),
            MailingListElement::find()->mailingListTypeId(6),
        ];

        foreach ($elementQueries as $elementQuery) {
            Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        }

        $count = ElementQueryRecord::find()
            ->joinWith('elementQuerySources', false)
            ->where(['not', ['sourceId' => null]])
            ->count();

        $this->assertEquals(7, $count);

        $sourceIds = ElementQuerySourceRecord::find()
            ->select('sourceId')
            ->column();

        // Assert that the source IDs were saved
        $this->assertEquals([1, 1, 2, 3, 4, 5, 6], $sourceIds);
    }

    public function testSaveElementQueryAttributeRecords()
    {
        $elementQuery = Entry::find()
            ->title('x');
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        $attributes = ElementQueryAttributeRecord::find()
            ->select('attribute')
            ->column();

        $this->assertEquals(['postDate', 'title'], $attributes);
    }

    public function testSaveElementQueryAttributeRecordsWithOrderBy()
    {
        $elementQuery = Entry::find()
            ->orderBy('title');
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        $attributes = ElementQueryAttributeRecord::find()
            ->select('attribute')
            ->column();

        $this->assertEquals(['title'], $attributes);
    }

    public function testSaveElementQueryAttributeRecordsWithOrderByPartsArray()
    {
        $elementQuery = Entry::find()
            ->orderBy(['entries.title' => SORT_DESC]);
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        $attributes = ElementQueryAttributeRecord::find()
            ->select('attribute')
            ->column();

        $this->assertEquals(['title'], $attributes);
    }

    public function testSaveElementQueryAttributeRecordsWithBefore()
    {
        $elementQuery = Entry::find()
            ->before('1999-12-31')
            ->orderBy('title');
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        $attributes = ElementQueryAttributeRecord::find()
            ->select('attribute')
            ->column();

        $this->assertEquals(['postDate', 'title'], $attributes);
    }

    public function testSaveElementQueryFieldRecordsWithOrderBy()
    {
        $elementQuery = Entry::find()
            ->orderBy('text');
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        $fieldIds = ElementQueryFieldRecord::find()
            ->select('fieldId')
            ->column();

        $this->assertEquals(FieldHelper::getFieldIdsFromHandles(['text']), $fieldIds);
    }

    public function testSaveElementQueryFieldRecordsWithOrderByArray()
    {
        $elementQuery = Entry::find()
            ->orderBy(['text' => SORT_ASC]);
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        $fieldIds = ElementQueryFieldRecord::find()
            ->select('fieldId')
            ->column();

        $this->assertEquals(FieldHelper::getFieldIdsFromHandles(['text']), $fieldIds);
    }

    public function testSaveCacheTags()
    {
        $tags = ['tag1', 'tag2', 'tag3'];
        Blitz::$plugin->generateCache->options->tags = $tags;

        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $cacheIds = Blitz::$plugin->cacheTags->getCacheIds(['xyz']);

        // Assert that zero cache IDs were found
        $this->assertCount(0, $cacheIds);

        $cacheIds = Blitz::$plugin->cacheTags->getCacheIds($tags);

        // Assert that one cache ID was found
        $this->assertCount(1, $cacheIds);
    }

    public function testSaveInclude()
    {
        Blitz::$plugin->generateCache->saveInclude(1, 't', []);

        $count = IncludeRecord::find()->count();

        // Assert that the record was saved
        $this->assertEquals(1, $count);
    }

    public function testSaveSsiInclude()
    {
        [$includeId] = Blitz::$plugin->generateCache->saveInclude(1, 't', []);
        Blitz::$plugin->generateCache->addSsiInclude($includeId);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $count = SsiIncludeCacheRecord::find()->count();

        // Assert that the record was saved
        $this->assertEquals(1, $count);
    }
}
