<?php

/**
 * Tests hints functionality.
 */

use putyourlightson\blitz\records\HintRecord;

beforeEach(function() {
    HintRecord::deleteAll();
});

test('Hint is recorded for a related element query that is lazy-loaded with the correct field ID', function() {
    saveHint();

    /** @var HintRecord $hint */
    $hint = HintRecord::find()->one();
    $field = Craft::$app->getFields()->getFieldByHandle('relatedTo');

    expect(HintRecord::find()->count())
        ->toBe(1)
        ->and($hint->fieldId)
        ->toEqual($field->id);
});

test('Hint is not recorded for a related element query that is lazy-loaded in a template that exist in the vendor folder path', function() {
    saveHint(template: Craft::getAlias('@vendor/templates/test'));

    expect(HintRecord::find()->count())
        ->toEqual(0);
});
