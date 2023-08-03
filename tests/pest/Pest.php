<?php

use craft\base\Element;
use craft\db\ActiveRecord;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use markhuot\craftpest\factories\Asset as AssetFactory;
use markhuot\craftpest\factories\Entry as EntryFactory;
use markhuot\craftpest\http\RequestBuilder;
use markhuot\craftpest\test\TestCase;
use markhuot\craftpest\web\TestableResponse;
use putyourlightson\blitz\behaviors\ElementChangedBehavior;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\FieldHelper;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\records\ElementExpiryDateRecord;

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

uses(TestCase::class)->in('./');

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

expect()->extend('toBeTracked', function (string $changedBy = '', array $changedAttributes = [], array $changedFields = []) {
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

expect()->extend('toNotBeTracked', function () {
    /** @var Element|ElementChangedBehavior|null $element */
    $element = $this->value;
    $refreshData = Blitz::$plugin->refreshCache->refreshData;

    expect($refreshData->getElementIds($element::class))
        ->not()->toContain($element->id);

    return $this;
});

expect()->extend('toBeChangedByFile', function () {
    /** @var Element|ElementChangedBehavior|null $element */
    $element = $this->value;

    expect($element)
        ->toBeTracked()
        ->and(Blitz::$plugin->refreshCache->refreshData->getAssetsChangedByFile())
        ->toBe([$element->id]);

    return $this;
});

expect()->extend('toExpireOn', function (?DateTime $expiryDate) {
    /** @var Entry $entry */
    $entry = $this->value;

    $elementExpiryDateRecord = ElementExpiryDateRecord::find()
        ->where(['elementId' => $entry->id])
        ->one();

    expect($elementExpiryDateRecord->expiryDate)
        ->toBe(Db::prepareDateForDb($expiryDate));

    return $this;
});

expect()->extend('toHaveRecordCount', function (int $count, array $where = []) {
    /** @var ActiveRecord $class */
    $class = $this->value;
    $recordCount = $class::find()->where($where)->count();

    expect($recordCount)
        ->toEqual($count);

    return $this;
});

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

function createEntry(): Entry
{
    $entry = EntryFactory::factory()
        ->section('blog')
        ->create();

    $entry->attachBehavior(ElementChangedBehavior::BEHAVIOR_NAME, ElementChangedBehavior::class);

    Blitz::$plugin->generateCache->reset();
    Blitz::$plugin->refreshCache->reset();

    return $entry;
}

function createEntryWithRelationship(): Entry
{
    $relatedEntry = createEntry();
    $entry = createEntry();
    $entry->relatedTo = [$relatedEntry->id];
    Craft::$app->elements->saveElement($entry);

    Blitz::$plugin->generateCache->reset();
    Blitz::$plugin->refreshCache->reset();

    return $entry;
}

function createAsset(): Asset
{
    $asset = AssetFactory::factory()
        ->volume('test')
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

function sendRequest(string $uri = ''): TestableResponse
{
    return (new RequestBuilder('get', $uri))->send();
}

