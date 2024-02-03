<?php

use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\commerce\records\Product as ProductRecord;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fs\Local;
use craft\helpers\App;
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
use markhuot\craftpest\web\TestableResponse;
use putyourlightson\blitz\behaviors\ElementChangedBehavior;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\IntegrationHelper;
use putyourlightson\blitz\models\SiteUriModel;
use yii\web\Response;

function getSiteId(): int
{
    return Craft::$app->sites->getSiteByHandle(App::env('TEST_SITE_HANDLE'))->id;
}

function getChannelSectionId(): int
{
    return Craft::$app->sections->getSectionByHandle(App::env('TEST_CHANNEL_SECTION_HANDLE'))->id;
}

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

function createEntry(bool $enabled = true, bool $batchMode = false): Entry
{
    $originalBatchMode = Blitz::$plugin->refreshCache->batchMode;
    Blitz::$plugin->refreshCache->batchMode = $batchMode;

    $entry = EntryFactory::factory()
        ->section(App::env('TEST_CHANNEL_SECTION_HANDLE'))
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
    $entry->setFieldValue('relatedTo', $relatedEntryIds);
    Craft::$app->elements->saveElement($entry);

    Blitz::$plugin->generateCache->reset();
    Blitz::$plugin->refreshCache->reset();

    return $entry;
}

function createAsset(): Asset
{
    $volume = Craft::$app->volumes->getVolumeByHandle(App::env('TEST_VOLUME_HANDLE'));
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

function createProductVariantOrder(bool $batchMode = false): array
{
    $originalBatchMode = Blitz::$plugin->refreshCache->batchMode;
    Blitz::$plugin->refreshCache->batchMode = $batchMode;
    $faker = FakerFactory::create();

    $type = Plugin::getInstance()->productTypes->getProductTypeByHandle(App::env('TEST_PRODUCT_TYPE_HANDLE'));
    $product = new Product([
        'title' => $faker->sentence(),
        'typeId' => $type->id,
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

function sendRequest(string $uri = ''): TestableResponse
{
    $response = (new RequestBuilder('get', $uri))->send();
    $response->trigger(Response::EVENT_AFTER_PREPARE);

    return $response;
}

function cleanup(): void
{
    $section = Craft::$app->sections->getSectionByHandle(App::env('TEST_CHANNEL_SECTION_HANDLE'));
    $entryIds = EntryRecord::find()
        ->select('id')
        ->where(['sectionId' => $section->id])
        ->column();

    $volume = Craft::$app->volumes->getVolumeByHandle(App::env('TEST_VOLUME_HANDLE'));
    $assetIds = AssetRecord::find()
        ->select('id')
        ->where(['volumeId' => $volume->id])
        ->column();

    $type = Plugin::getInstance()->productTypes->getProductTypeByHandle(App::env('TEST_PRODUCT_TYPE_HANDLE'));
    $productIds = ProductRecord::find()
        ->select('id')
        ->where(['typeId' => $type->id])
        ->column();

    ElementRecord::deleteAll(['id' => array_merge($entryIds, $assetIds, $productIds)]);

    Craft::$app->elements->invalidateAllCaches();

    Db::delete(Table::TOKENS, ['route' => 'blitz/test']);
    Db::delete(Table::TOKENS, ['route' => 'blitz/generator/generate']);

    $volume = Craft::$app->volumes->getVolumeByHandle(App::env('TEST_VOLUME_HANDLE'));
    /** @var Local $fs */
    $fs = $volume->getFs();
    FileHelper::clearDirectory($fs->path);
}
