<?php

/**
 * Tests hints functionality.
 */

use craft\elements\db\ElementQuery;
use craft\elements\Entry;
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

test('Hint is recorded for a related element query that is lazy-loaded', function() {
    Entry::find()->section('single')->one()->relatedTo->all();
    Blitz::$plugin->hints->save();

    expect(HintRecord::find()->count())
        ->toBe(1);
});

test('Hint is not recorded for a related element query that is lazy eager-loaded', function() {
    Entry::find()->section('single')->one()->relatedTo->eagerly()->all();
    Blitz::$plugin->hints->save();

    expect(HintRecord::find()->count())
        ->toEqual(0);
});
