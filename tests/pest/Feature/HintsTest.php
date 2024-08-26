<?php

/**
 * Tests hints functionality.
 */

use craft\elements\db\ElementQuery;
use craft\events\CancelableEvent;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\HintModel;
use putyourlightson\blitz\records\HintRecord;
use putyourlightson\blitz\services\HintsService;
use yii\base\Event;

beforeEach(function() {
    Blitz::$plugin->settings->hintsEnabled = true;
    HintRecord::deleteAll();

    $fieldId = Craft::$app->getFields()->getFieldByHandle('relatedTo')->id;
    $hints = Mockery::mock(HintsService::class)->makePartial();
    $hints->shouldAllowMockingProtectedMethods();
    $hints->shouldReceive('createHintWithTemplateLine')->andReturn(new HintModel([
        'fieldId' => $fieldId,
        'template' => 'templates/test',
    ]));
    Blitz::$plugin->set('hints', $hints);

    /** @see Blitz::registerHintsUtilityEvents() */
    Event::on(ElementQuery::class, ElementQuery::EVENT_BEFORE_PREPARE,
        function(CancelableEvent $event) {
            /** @var ElementQuery $elementQuery */
            $elementQuery = $event->sender;
            Blitz::$plugin->hints->checkElementQuery($elementQuery);
        },
        null,
        false
    );
});

test('Hint is recorded for a matrix element query that is lazy-loaded', function() {
    getSingleEntry()->matrix->all();
    Blitz::$plugin->hints->save();

    expect(HintRecord::find()->count())
        ->toBe(1);
});

test('Hint is not recorded for a matrix element query that is lazy eager-loaded', function() {
    getSingleEntry()->matrix->eagerly()->all();
    Blitz::$plugin->hints->save();

    expect(HintRecord::find()->count())
        ->toBe(0);
});

test('Hint is recorded for a related entry query that is lazy-loaded', function() {
    getSingleEntry()->relatedTo->all();
    Blitz::$plugin->hints->save();

    expect(HintRecord::find()->count())
        ->toBe(1);
});

test('Hint is not recorded for a related entry query that is lazy eager-loaded', function() {
    getSingleEntry()->relatedTo->eagerly()->all();
    Blitz::$plugin->hints->save();

    expect(HintRecord::find()->count())
        ->toBe(0);
});

test('Hint is recorded for a related category query that is lazy-loaded', function() {
    getSingleEntry()->categories->all();
    Blitz::$plugin->hints->save();

    expect(HintRecord::find()->count())
        ->toBe(1);
});

test('Hint is not recorded for a related category query that is lazy eager-loaded', function() {
    getSingleEntry()->categories->eagerly()->all();
    Blitz::$plugin->hints->save();

    expect(HintRecord::find()->count())
        ->toBe(0);
});

test('Hint is not recorded for element queries with reference tags', function() {
    getSingleEntry()->relatedTo->ref('{entry:1@1:url}')->all();
    Blitz::$plugin->hints->save();

    expect(HintRecord::find()->count())
        ->toBe(0);
});

test('Hint is not recorded for parsed reference tags', function() {
    Craft::$app->getElements()->parseRefs('{entry:1@1:url}');
    Blitz::$plugin->hints->save();

    expect(HintRecord::find()->count())
        ->toBe(0);
});
