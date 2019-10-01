<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use Craft;
use putyourlightson\blitz\models\SiteUriModel;

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
    public function warm(SiteUriModel $siteUri, int $delay = null)
    {
    }

    /**
     * @inheritdoc
     */
    public function requestUrls(array $urls, callable $setProgressHandler): int
    {
        return 0;
    }
}
