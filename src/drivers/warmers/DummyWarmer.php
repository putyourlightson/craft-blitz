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
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'None');
    }

    /**
     * @inheritdoc
     */
    public bool $isDummy = true;

    /**
     * @inheritdoc
     */
    public function warmUris(array $siteUris, callable $setProgressHandler = null, bool $queue = true)
    {
    }

    /**
     * @inheritdoc
     */
    public function warmSite(int $siteId, callable $setProgressHandler = null, bool $queue = true)
    {
    }

    /**
     * @inheritdoc
     */
    public function warmAll(callable $setProgressHandler = null, bool $queue = true)
    {
    }
}
