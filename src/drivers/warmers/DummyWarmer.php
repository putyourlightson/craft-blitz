<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use Craft;

/**
 * @since 3.6.13
 */
class DummyWarmer extends BaseCacheWarmer
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'None');
    }

    /**
     * @var bool
     */
    public bool $isDummy = true;

    /**
     * @inheritdoc
     */
    public function warmUris(array $siteUris, callable $setProgressHandler = null, int $delay = null, bool $queue = true) { }

    /**
     * @inheritdoc
     */
    public function warmSite(int $siteId, callable $setProgressHandler = null, int $delay = null, bool $queue = true) { }

    /**
     * @inheritdoc
     */
    public function warmAll(callable $setProgressHandler = null, int $delay = null, bool $queue = true) { }
}
