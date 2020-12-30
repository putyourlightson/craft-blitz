<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\db\Table;
use craft\errors\SiteNotFoundException;
use craft\helpers\Db;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\SiteUriHelper;

/**
 * @property mixed $settingsHtml
 */
class CloudflarePurger extends BaseCachePurger
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
    public $authenticationMethod = 'apiKey';

    /**
     * @var string
     */
    public $apiToken;

    /**
     * @var string
     */
    public $apiKey;

    /**
     * @var string
     */
    public $email;

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
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['parser'] = [
            'class' => EnvAttributeParserBehavior::class,
            'attributes' => [
                'apiToken',
                'apiKey',
                'email',
            ],
        ];

        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'apiToken' => Craft::t('blitz', 'API Token'),
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
            [['apiToken'], 'required', 'when' => function(CloudflarePurger $purger) {
                return $purger->authenticationMethod == 'apiToken';
            }],
            [['apiKey', 'email'], 'required', 'when' => function(CloudflarePurger $purger) {
                return $purger->authenticationMethod == 'apiKey';
            }],
            [['email'], 'email'],
            [['warmCacheDelay'], 'integer', 'min' => 0, 'max' => 30],
        ];
    }

    /**
     * @inheritdoc
     */
    public function purgeUris(array $siteUris)
    {
        $siteUris = $this->beforePurgeCache($siteUris);

        $groupedSiteUris = SiteUriHelper::getSiteUrisGroupedBySite($siteUris);

        foreach ($groupedSiteUris as $siteId => $siteUriGroup) {
            $this->_sendRequest('delete', 'purge_cache', $siteId, [
                'files' => SiteUriHelper::getUrlsFromSiteUris($siteUriGroup)
            ]);
        }

        $this->afterPurgeCache($siteUris);
    }

    /**
     * @inheritdoc
     */
    public function purgeSite(int $siteId)
    {
        $this->_sendRequest('delete', 'purge_cache', $siteId, [
            'purge_everything' => true
        ]);
    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        foreach ($this->zoneIds as $siteUid => $value) {
            if ($value['zoneId']) {
                try {
                    $site = Craft::$app->getSites()->getSiteByUid($siteUid);
                }
                catch (SiteNotFoundException $e) {
                    Blitz::$plugin->log($e->getMessage(), [], 'error');

                    continue;
                }

                if ($site !== null) {
                    $response = $this->_sendRequest('get', '', $site->id);

                    if ($response === false) {
                        $error = Craft::t('blitz', 'Error connecting to Cloudflare using zone ID for “{site}”.', ['site' => $site->name]);

                        $this->addError('zoneIds', $error);
                    }
                }
            }
        }

        return !$this->hasErrors();
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
     * @param array $params
     *
     * @return bool
     */
    private function _sendRequest(string $method, string $action, int $siteId, array $params = []): bool
    {
        $response = false;

        $headers = ['Content-Type' => 'application/json'];

        if ($this->authenticationMethod == 'apiKey') {
            $headers['X-Auth-Key'] = Craft::parseEnv($this->apiKey);
            $headers['X-Auth-Email'] = Craft::parseEnv($this->email);
        }
        else {
            $headers['Authorization'] = 'Bearer '.Craft::parseEnv($this->apiToken);
        }

        $client = Craft::createGuzzleClient([
            'base_uri' => self::API_ENDPOINT,
            'headers'  => $headers,
        ]);

        $siteUid = Db::uidById(Table::SITES, $siteId);

        if ($siteUid === null) {
            return false;
        }

        if (empty($this->zoneIds[$siteUid]) || empty($this->zoneIds[$siteUid]['zoneId'])) {
            return false;
        }

        $uri = 'zones/'.Craft::parseEnv($this->zoneIds[$siteUid]['zoneId']).'/'.$action;

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
                        Blitz::$plugin->log(trim($matches[1], ':'), [], 'error');
                    }
                }
            },
        ]);

        // Initiate the transfers and wait for the pool of requests to complete
        $pool->promise()->wait();

        return $response;
    }
}
