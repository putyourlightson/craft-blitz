<?php

/**
 * Tests the architecture of the plugin.
 */

test('Source code does not contain any “var_dump” or “die” statements')
    ->expect(['var_dump', 'die'])
    ->not->toBeUsed();
