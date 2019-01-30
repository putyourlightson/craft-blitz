<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use Craft;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;

/**
 * @property mixed $settingsHtml
 */
class KeyCdnPurger extends BaseCachePurger
{
    // Constants
    // =========================================================================

    const API_ENDPOINT = 'https://api.keycdn.com/';

    // Properties
    // =========================================================================

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
        return Craft::t('blitz', 'Key CDN Purger');
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
    public function rules()
    {
        return [
            [['apiKey', 'zoneId'], 'required'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function purge(SiteUriModel $siteUri)
    {
        $this->_sendRequest('delete', 'purgeurl', [
            'urls' => [$siteUri->getUrl()]
        ]);
    }

    /**
     * @inheritdoc
     */
    public function purgeUris(array $siteUris)
    {
        $this->_sendRequest('delete', 'purgeurl', [
            'urls' => SiteUriHelper::getUrls($siteUris)
        ]);
    }

    /**
     * @inheritdoc
     */
    public function purgeAll()
    {
        $this->_sendRequest('get', 'purge');
    }

    /**
     * @inheritdoc
     */
    public function test(): bool
    {
        $response = $this->_sendRequest('get');

        if (!$response) {
            return false;
        }

        return $response->getStatusCode() == 200;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/purgers/keycdn/settings', [
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
     * @return ResponseInterface|string
     */
    private function _sendRequest(string $method, string $action = '', array $params = [])
    {
        $response = '';

        $client = Craft::createGuzzleClient();

        $uri = 'zones/'.($action ? $action.'/' : '').$this->zoneId.'.json';
        $options = [
            'base_uri' => self::API_ENDPOINT,
            'headers'  => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic '.base64_encode($this->apiKey.':'),
            ]
        ];

        if (!empty($params)) {
            $options['json'] = $params;
        }

        try {
            $response = $client->request($method, $uri, $options);
        }
        catch (BadResponseException $e) { Craft::dd($e); }
        catch (GuzzleException $e) { }

        return $response;
    }
}