<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use Craft;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;
use yii\log\Logger;

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
    public $email;

    /**
     * @var string
     */
    public $apiKey;

    /**
     * @var string
     */
    public $zoneId;

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
            'zoneId' => Craft::t('blitz', 'Zone ID'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['apiKey', 'email', 'zoneId'], 'required'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function purge(SiteUriModel $siteUri)
    {
        $this->_sendRequest('delete', 'purge_cache', [
            'files' => [$siteUri->getUrl()]
        ]);
    }

    /**
     * @inheritdoc
     */
    public function purgeUris(array $siteUris)
    {
        $this->_sendRequest('delete', 'purge_cache', [
            'files' => SiteUriHelper::getUrls($siteUris)
        ]);
    }

    /**
     * @inheritdoc
     */
    public function purgeAll()
    {
        $this->_sendRequest('delete', 'purge_cache', [
            'purge_everything' => true
        ]);
    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        $response = $this->_sendRequest('get');

        return $response;
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
     * @param string|null $action
     * @param array|null $params
     *
     * @return bool
     */
    private function _sendRequest(string $method, string $action = '', array $params = []): bool
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

        $uri = 'zones/'.Craft::parseEnv($this->zoneId).'/'.$action;

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

        // Create a pool of requests for sending multiple concurrent requests
        $pool = new Pool($client, $requests, [
            'concurrency' => Blitz::$plugin->settings->concurrency,
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