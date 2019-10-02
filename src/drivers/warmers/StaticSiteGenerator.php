<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\warmers;

use Craft;
use craft\queue\QueueInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\jobs\WarmCacheJob;
use putyourlightson\blitz\models\SiteUriModel;
use yii\log\Logger;

/**
 * @property mixed $settingsHtml
 */
class StaticSiteGenerator extends DefaultWarmer
{
    // Properties
    // =========================================================================

    /**
     * @var string|null
     */
    public $gitPath;

    /**
     * @var string
     */
    public $gitCommitMessage = 'Blitz auto commit.';

    /**
     * @var string
     */
    public $gitBranch = 'master';

    /**
     * @var string[]
     */
    public $extraUrls = [];

    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Static Site Generator');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function warmAll()
    {
        $urls = array_unique(array_merge(
            SiteUriHelper::getAllSiteUris(true),
            $this->extraUrls
        ));

        $this->warmUris($urls);
    }

    /**
     * @inheritdoc
     */
    public function requestUrls(array $urls, callable $setProgressHandler): int
    {
        $success = parent::requestUrls($urls, $setProgressHandler);

        if ($success > 0 && $this->gitPath) {
            $this->gitCommitPush();
        }

        return $success;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/warmers/static-site-generator/settings', [
            'warmer' => $this,
        ]);
    }

    // Protected Methods
    // =========================================================================

    /**
     * Commits and pushes a git repository.
     */
    protected function gitCommitPush()
    {
        require_once('Git.php');

        $repo = Git::open($this->gitPath);

        $repo->add('.');
        $repo->commit($this->gitCommitMessage);
        $repo->push('origin', $this->gitBranch);
    }
}
