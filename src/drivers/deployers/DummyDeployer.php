<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\deployers;

use Craft;

class DummyDeployer extends BaseDeployer
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

    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    public $isDummy = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function deployUris(array $siteUris, callable $setProgressHandler = null, bool $queue = true) { }

    /**
     * @inheritdoc
     */
    public function deploySite(int $siteId, callable $setProgressHandler = null, bool $queue = true) { }

    /**
     * @inheritdoc
     */
    public function deployAll(callable $setProgressHandler = null) { }
}
