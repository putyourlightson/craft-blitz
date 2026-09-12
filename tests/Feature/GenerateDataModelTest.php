<?php

use craft\elements\Entry;
use putyourlightson\blitz\helpers\FieldHelper;
use putyourlightson\blitz\models\BaseDataModel;
use putyourlightson\blitz\models\GenerateDataModel;

test('Generated dependencies distinguish localized variants and retain unknown sites', function() {
    $data = new GenerateDataModel();
    $entry = createEntry();
    $otherSiteId = null;
    foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
        if ($siteId !== $entry->siteId) {
            $otherSiteId = $siteId;
            break;
        }
    }
    expect($otherSiteId)->not->toBeNull();
    $otherVariant = Entry::find()->id($entry->id)->siteId($otherSiteId)->status(null)->one();
    expect($otherVariant)->not->toBeNull();

    $siteSpecificEntry = createEntry();
    $unknownSiteEntry = createEntry();
    $data->addElement($entry);
    $data->addElement($entry);
    $data->addElement($otherVariant);
    $data->addElementIds([$siteSpecificEntry->id], $otherSiteId);
    $data->addElementId($unknownSiteEntry->id);

    expect($data->getElementIds())->toBe([$entry->id, $siteSpecificEntry->id, $unknownSiteEntry->id])
        ->and($data->getElementSiteIds())->toBe([
            $entry->id => [$entry->siteId, $otherSiteId],
            $siteSpecificEntry->id => [$otherSiteId],
            $unknownSiteEntry->id => [BaseDataModel::SITE_ID_ANY],
        ])
        ->and($data->getElementIds($entry->siteId))->toBe([$entry->id, $unknownSiteEntry->id])
        ->and($data->getElementIds($otherSiteId))->toBe([$entry->id, $siteSpecificEntry->id, $unknownSiteEntry->id])
        ->and($data->getElementIds(9999))->toBe([$unknownSiteEntry->id]);

    $restored = new GenerateDataModel(['data' => $data->data]);
    expect($restored->getElementIds($entry->siteId))->toBe([$entry->id, $unknownSiteEntry->id]);
});

test('Generated field dependencies keep each sites accessed fields separate', function() {
    $data = new GenerateDataModel();
    $entry = createEntry();
    $otherSiteId = null;
    foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
        if ($siteId !== $entry->siteId) {
            $otherSiteId = $siteId;
            break;
        }
    }
    expect($otherSiteId)->not->toBeNull();
    $otherVariant = Entry::find()->id($entry->id)->siteId($otherSiteId)->status(null)->one();
    expect($otherVariant)->not->toBeNull();

    $otherEntry = createEntry();
    $layout = $entry->getFieldLayout();
    expect($layout)->not->toBeNull();
    $firstFieldUid = FieldHelper::getFieldInstanceUidForFieldLayout($layout, 'plainText');
    $secondFieldUid = FieldHelper::getFieldInstanceUidForFieldLayout($layout, 'relatedTo');
    $sharedFieldUid = FieldHelper::getFieldInstanceUidForFieldLayout($layout, 'matrix');
    expect($firstFieldUid)->not->toBeNull()
        ->and($secondFieldUid)->not->toBeNull()
        ->and($sharedFieldUid)->not->toBeNull();

    $data->addElementTrackField($entry, 'plainText');
    $data->addElementTrackField($otherVariant, 'relatedTo');
    $data->addElementIdsTrackField([$entry->id], $layout, 'matrix');
    $data->addElementIdsTrackField([$otherEntry->id], $layout, 'relatedTo', $otherSiteId);
    $data->addElementIdsTrackField([$entry->id], $layout, 'missing');

    expect($data->getElementTrackFields($entry->siteId))->toBe([$entry->id => [$firstFieldUid, $sharedFieldUid]])
        ->and($data->getElementTrackFields($otherSiteId))->toBe([
            $entry->id => [$secondFieldUid, $sharedFieldUid],
            $otherEntry->id => [$secondFieldUid],
        ])
        ->and($data->getElementTrackFields(9999))->toBe([$entry->id => [$sharedFieldUid]])
        ->and($data->getElementTrackFields())->toBe([
            $entry->id => [$firstFieldUid, $secondFieldUid, $sharedFieldUid],
            $otherEntry->id => [$secondFieldUid],
        ]);

    $restored = new GenerateDataModel(['data' => $data->data]);
    expect($restored->getElementTrackFields($entry->siteId))->toBe([$entry->id => [$firstFieldUid, $sharedFieldUid]]);
});

test('Generated dependencies without site metadata remain valid for every site', function() {
    $entry = createEntry();
    $data = new GenerateDataModel([
        'data' => [
            'elements' => [
                'elementIds' => [$entry->id => true],
                'trackFields' => [$entry->id => ['field-uid' => true]],
            ],
        ],
    ]);

    expect($data->getElementSiteIds())->toBe([$entry->id => [BaseDataModel::SITE_ID_ANY]])
        ->and($data->getElementIds($entry->siteId))->toBe([$entry->id])
        ->and($data->getElementTrackFields($entry->siteId))->toBe([$entry->id => ['field-uid']]);

    $data->addElementId($entry->id, $entry->siteId);
    expect($data->getElementIds(9999))->toBe([$entry->id]);
});
