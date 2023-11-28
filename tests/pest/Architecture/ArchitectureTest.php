<?php

/**
 * Tests the architecture of the plugin.
 */

test('Code does not contain “die” statements')
    ->expect(['dd', 'die'])
    ->not->toBeUsed();
