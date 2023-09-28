<?php

use craft\base\Element;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\LineItem;
use craft\commerce\records\Product as ProductRecord;
use craft\db\ActiveRecord;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\records\Asset as AssetRecord;
use craft\records\Element as ElementRecord;
use craft\records\Entry as EntryRecord;
use Faker\Factory as FakerFactory;
use markhuot\craftpest\factories\Asset as AssetFactory;
use markhuot\craftpest\factories\Entry as EntryFactory;
use markhuot\craftpest\http\RequestBuilder;
use markhuot\craftpest\test\TestCase;
use markhuot\craftpest\web\TestableResponse;
use putyourlightson\blitz\behaviors\ElementChangedBehavior;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\FieldHelper;
use putyourlightson\blitz\helpers\IntegrationHelper;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\ElementExpiryDateRecord;
use yii\web\Response;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)
    ->beforeAll(function() {
        // Clear the cache directory without using Blitz, which is not yet instantiated.
        FileHelper::clearDirectory(getcwd() . '/web/cache');
    })
    ->afterAll(function() {
        cleanUpElements();
        Craft::$app->queue->releaseAll();
        Blitz::$plugin->cacheStorage->deleteAll();
    })
    ->in('./');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toContainOnce', function(...$needles) {
    foreach ($needles as $needle) {
        expect($this->value)
            ->toContain($needle);
    }

    return $this;
});

expect()->extend('toBeTracked', function(string $changedBy = '', array $changedAttributes = [], array $changedFields = []) {
    /** @var Element|ElementChangedBehavior|null $element */
    $element = $this->value;
    $refreshData = Blitz::$plugin->refreshCache->refreshData;
    $changedFieldIds = FieldHelper::getFieldIdsFromHandles($changedFields);

    if ($element === null) {
        return expect($refreshData->isEmpty())
            ->toBeTrue();
    }

    expect($refreshData->getElementIds($element::class))
        ->toEqual([$element->id])
        ->and($refreshData->getSourceIds($element::class))
        ->toEqual(!empty($element->sectionId) ? [$element->sectionId] : [])
        ->and($refreshData->getChangedAttributes($element::class, $element->id))
        ->toEqual($changedAttributes)
        ->and($refreshData->getChangedFields($element::class, $element->id))
        ->toEqual($changedFieldIds);

    if ($changedBy === 'attributes') {
        expect($refreshData->getIsChangedByAttributes(Entry::class, $element->id))
            ->toBeTrue()
            ->and($refreshData->getIsChangedByFields(Entry::class, $element->id))
            ->toBeFalse();
    } elseif ($changedBy === 'fields') {
        expect($refreshData->getIsChangedByAttributes(Entry::class, $element->id))
            ->toBeFalse()
            ->and($refreshData->getIsChangedByFields(Entry::class, $element->id))
            ->toBeTrue();
    } else {
        expect($refreshData->getIsChangedByAttributes(Entry::class, $element->id))
            ->toBeFalse()
            ->and($refreshData->getIsChangedByFields(Entry::class, $element->id))
            ->toBeFalse();
    }

    return $this;
});

expect()->extend('toNotBeTracked', function() {
    /** @var Element|ElementChangedBehavior|null $element */
    $element = $this->value;
    $refreshData = Blitz::$plugin->refreshCache->refreshData;

    expect($refreshData->getElementIds($element::class))
        ->not()->toContain($element->id);

    return $this;
});

expect()->extend('toBeChangedByFile', function() {
    /** @var Element|ElementChangedBehavior|null $element */
    $element = $this->value;

    expect($element)
        ->toBeTracked()
        ->and(Blitz::$plugin->refreshCache->refreshData->getAssetsChangedByFile())
        ->toBe([$element->id]);

    return $this;
});

expect()->extend('toExpireOn', function(?DateTime $expiryDate) {
    /** @var Entry $entry */
    $entry = $this->value;

    $elementExpiryDateRecord = ElementExpiryDateRecord::find()
        ->where(['elementId' => $entry->id])
        ->one();

    expect($elementExpiryDateRecord->expiryDate)
        ->toBe(Db::prepareDateForDb($expiryDate));

    return $this;
});

expect()->extend('toHaveRecordCount', function(int $count, array $where = []) {
    /** @var ActiveRecord $class */
    $class = $this->value;
    $recordCount = $class::find()->where($where)->count();

    expect($recordCount)
        ->toEqual($count);

    return $this;
});

/*
|--------------------------------------------------------------------------
| Constants
|--------------------------------------------------------------------------
*/

const TEST_SITE_ID = 1;
const TEST_SECTION_ID = 12;
const TEST_VOLUME_ID = 2;
const TEST_PRODUCT_TYPE_ID = 2;

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function integrationIsActive(string $class): bool
{
    return in_array($class, IntegrationHelper::getActiveIntegrations());
}

function createOutput(): string
{
    return StringHelper::randomString();
}

function createSiteUri(int $siteId = 1, string $uri = 'page'): SiteUriModel
{
    return new SiteUriModel([
        'siteId' => $siteId,
        'uri' => $uri,
    ]);
}

function createEntry(int $sectionId = TEST_SECTION_ID, bool $enabled = true, bool $batchMode = false): Entry
{
    $originalBatchMode = Blitz::$plugin->refreshCache->batchMode;
    Blitz::$plugin->refreshCache->batchMode = $batchMode;

    $entry = EntryFactory::factory()
        ->section($sectionId)
        ->enabled($enabled)
        ->create();

    $entry->attachBehavior(ElementChangedBehavior::BEHAVIOR_NAME, ElementChangedBehavior::class);

    Blitz::$plugin->generateCache->reset();
    Blitz::$plugin->refreshCache->reset();
    Blitz::$plugin->refreshCache->batchMode = $originalBatchMode;

    return $entry;
}

function createEntryWithRelationship(array $relatedEntries = []): Entry
{
    if (empty($relatedEntries)) {
        $relatedEntries = [createEntry()];
    }

    $relatedEntryIds = ArrayHelper::getColumn($relatedEntries, 'id');

    $entry = createEntry();
    $entry->relatedTo = $relatedEntryIds;
    Craft::$app->elements->saveElement($entry);

    Blitz::$plugin->generateCache->reset();
    Blitz::$plugin->refreshCache->reset();

    return $entry;
}

function createAsset(int $volumeId = TEST_VOLUME_ID): Asset
{
    $volume = Craft::$app->volumes->getVolumeById($volumeId);
    $asset = AssetFactory::factory()
        ->volume($volume->handle)
        ->create();

    $asset->setFocalPoint([
        'x' => 1,
        'y' => 1,
    ]);
    Craft::$app->elements->saveElement($asset);

    Blitz::$plugin->refreshCache->reset();

    $asset->attachBehavior(ElementChangedBehavior::BEHAVIOR_NAME, ElementChangedBehavior::class);

    return $asset;
}

function createProductVariantOrder(int $typeId = TEST_PRODUCT_TYPE_ID, bool $batchMode = false): array
{
    $originalBatchMode = Blitz::$plugin->refreshCache->batchMode;
    Blitz::$plugin->refreshCache->batchMode = $batchMode;
    $faker = FakerFactory::create();

    $product = new Product([
        'title' => $faker->sentence,
        'typeId' => $typeId,
    ]);
    Craft::$app->elements->saveElement($product);

    $variant = new Variant([
        'sku' => 'test-sku',
        'price' => 10,
        'stock' => 100,
        'productId' => $product->id,
    ]);
    Craft::$app->elements->saveElement($variant);

    $variant->attachBehavior(ElementChangedBehavior::BEHAVIOR_NAME, ElementChangedBehavior::class);

    $order = new Order();
    $lineItem = new LineItem([
        'qty' => 1,
        'purchasableId' => $variant->id,
        'taxCategoryId' => 1,
        'shippingCategoryId' => 1,
    ]);
    $order->addLineItem($lineItem);
    Craft::$app->getElements()->saveElement($order);

    Blitz::$plugin->generateCache->reset();
    Blitz::$plugin->refreshCache->reset();
    Blitz::$plugin->refreshCache->batchMode = $originalBatchMode;

    return [$variant, $order];
}

function sendRequest(string $uri = '', array $headers = []): TestableResponse
{
    $response = (new RequestBuilder('get', $uri))->send();
    $response->trigger(Response::EVENT_AFTER_PREPARE);

    return $response;
}

function cleanUpElements(): void
{
    $entryIds = EntryRecord::find()
        ->select('id')
        ->where(['sectionId' => TEST_SECTION_ID])
        ->column();
    $assetIds = AssetRecord::find()
        ->select('id')
        ->where(['volumeId' => TEST_VOLUME_ID])
        ->column();
    $productIds = ProductRecord::find()
        ->select('id')
        ->where(['typeId' => TEST_PRODUCT_TYPE_ID])
        ->column();
    ElementRecord::deleteAll(['id' => array_merge($entryIds, $assetIds, $productIds)]);

    Craft::$app->elements->invalidateAllCaches();
}
