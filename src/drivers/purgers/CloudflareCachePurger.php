<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use Craft;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use putyourlightson\blitz\helpers\SiteUriHelper;
use yii\log\Logger;

/**
 * @property mixed $settingsHtml
 */
class CloudflareCachePurger extends BaseCachePurger
{
    // Constants
    // =========================================================================

    const API_ENDPOINT = 'https://api.cloudflare.com/client/v4/';

    const API_URL_LIMIT = 30;

    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public $email;

    /**
     * @var string
     */
    public $apiKey;

    /**
     * @var array
     */
    public $zoneIds = [];

    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Cloudflare Purger');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'apiKey' => Craft::t('blitz', 'API Key'),
            'zoneIds' => Craft::t('blitz', 'Zone IDs'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['apiKey', 'email', 'warmCacheDelay'], 'required'],
            [['warmCacheDelay'], 'integer']
        ];
    }

    /**
     * @inheritdoc
     */
    public function purgeUris(array $siteUris)
    {
        $groupedSiteUris = SiteUriHelper::getSiteUrisGroupedBySite($siteUris);

        foreach ($groupedSiteUris as $siteId => $siteUris) {
            $this->_sendRequest('delete', 'purge_cache', $siteId, [
                'files' => SiteUriHelper::getUrls($siteUris)
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function purgeAll()
    {
        $sites = Craft::$app->getSites()->getAllSites();

        foreach ($sites as $site) {
            $this->_sendRequest('delete', 'purge_cache', $site->id, [
                'purge_everything' => true
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        foreach ($this->zoneIds as $siteUid => $value) {
            if ($value['zoneId']) {
                $site = Craft::$app->getSites()->getSiteByUid($siteUid);

                if ($site !== null) {
                    $response = $this->_sendRequest('get', '', $site->id);

                    if ($response === false) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/purgers/cloudflare/settings', [
            'purger' => $this,
        ]);
    }

    // Private Methods
    // =========================================================================

    /**
     * Sends a request to the API.
     *
     * @param string $method
     * @param string $action
     * @param int $siteId
     * @param array|null $params
     *
     * @return bool
     */
    private function _sendRequest(string $method, string $action, int $siteId, array $params = []): bool
    {
        $response = false;

        $client = Craft::createGuzzleClient([
            'base_uri' => self::API_ENDPOINT,
            'headers'  => [
                'Content-Type' => 'application/json',
                'X-Auth-Email' => Craft::parseEnv($this->email),
                'X-Auth-Key'   => Craft::parseEnv($this->apiKey),
            ]
        ]);

        $site = Craft::$app->getSites()->getSiteById($siteId);

        if ($site === null) {
            return false;
        }

        if (empty($this->zoneIds[$site->uid]) || empty($this->zoneIds[$site->uid]['zoneId'])) {
            return false;
        }

        $uri = 'zones/'.Craft::parseEnv($this->zoneIds[$site->uid]['zoneId']).'/'.$action;

        $requests = [];

        // If files requested then create requests from chunks to respect Cloudflare's limit
        if (!empty($params['files'])) {
            $files = $params['files'];
            $batches = array_chunk($files, self::API_URL_LIMIT);

            foreach ($batches as $batch) {
                $requests[] = new Request($method, $uri, [],
                    json_encode(['files' => $batch])
                );
            }
        }
        else {
            $requests[] = new Request($method, $uri, [], json_encode($params));
        }

        // Create a pool of requests
        $pool = new Pool($client, $requests, [
            'fulfilled' => function() use (&$response) {
                $response = true;
            },
            'rejected' => function($reason) {
                if ($reason instanceof RequestException) {
                    /** RequestException $reason */
                    preg_match('/^(.*?)\R/', $reason->getMessage(), $matches);

                    if (!empty($matches[1])) {
                        Craft::getLogger()->log(trim($matches[1], ':'), Logger::LEVEL_ERROR, 'blitz');
                    }
                }
            },
        ]);

        // Initiate the transfers and wait for the pool of requests to complete
        $pool->promise()->wait();

        return $response;
    }
}