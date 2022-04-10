<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\deployers;

use Craft;

class DummyDeployer extends BaseDeployer
{
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
    public function deployUris(array $siteUris, callable $setProgressHandler = null, bool $queue = true): void
    {
    }

    /**
     * @inheritdoc
     */
    public function deploySite(int $siteId, callable $setProgressHandler = null, bool $queue = true): void
    {
    }

    /**
     * @inheritdoc
     */
    public function deployAll(callable $setProgressHandler = null, bool $queue = true): void
    {
    }
}
