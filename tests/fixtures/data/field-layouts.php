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
                ],
            ],
        ],
    ],
];
