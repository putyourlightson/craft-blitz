<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\purgers;

use Craft;
use GuzzleHttp\Exception\BadResponseException;

/**
 * @property mixed $settingsHtml
 */
class CloudfrontPurger extends BasePurger
{
    // Constants
    // =========================================================================

    const API_ENDPOINT = 'https://api.cloudflare.com/client/v4/';

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
        return Craft::t('blitz', 'Cloudfront Purger');
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
            [['apiKey', 'email', 'zoneId'], 'required'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function purgeUrls(array $urls)
    {
        $this->_sendPurgeRequest(['files' => $urls]);
    }

    /**
     * @inheritdoc
     */
    public function purgeAll()
    {
        $this->_sendPurgeRequest(['purge_everything' => true]);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('blitz/_purgers/cloudflare/settings', [
            'purger' => $this,
        ]);
    }

    // Private Methods
    // =========================================================================

    /**
     * Sends a purge request to the API.
     *
     * @param array|null $params
     */
    private function _sendPurgeRequest(array $params = [])
    {
        $client = Craft::createGuzzleClient();

        try {
            $client->delete(
                self::API_ENDPOINT.'zones/'.$this->zoneId.'/purge_cache',
                [
                    'headers'  => [
                        'Content-Type' => 'application/json',
                        'X-Auth-Email' => $this->email,
                        'X-Auth-Key'   => $this->apiKey,
                    ],
                    'json' => $params,
                ]
            );
        }
        catch (BadResponseException $e) { }
    }
}