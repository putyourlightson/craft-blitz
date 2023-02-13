<?php

use craft\fields\PlainText;

return [
    [
        'type' => 'craft\test\Craft',
        'tabs' => [
            [
                'name' => 'Tab 1',
                'fields' => [
                    [
                        'name' => 'Text',
                        'handle' => 'text',
                        'type' => PlainText::class,
                        'required' => false,
                    ],
                    [
                        'name' => 'More Text',
                        'handle' => 'moreText',
                        'type' => PlainText::class,
                        'required' => false,
                    ],
                    [
                        'name' => 'Even More Text',
                        'handle' => 'evenMoreText',
                        'type' => PlainText::class,
                        'required' => false,
                    ],
                ],
            ],
        ],
    ],
    [
        // Because User elements fetch layout by type
        'type' => 'craft\elements\User',
        'tabs' => [
            [
                'name' => 'Tab 1',
                'fields' => [
                    [
                        'name' => 'Short Biography',
                        'handle' => 'shortBio',
                        'type' => PlainText::class,
                        'required' => false,
                    ],
                ],
            ],
        ],
    ],
];
