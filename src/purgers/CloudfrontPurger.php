<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\purgers;

use Craft;

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
    public $apiKey;

    /**
     * @var string
     */
    public $apiEmail;

    /**
     * @var string
     */
    public $zoneId;

    /**
     * @var string
     */
    public $domain;

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
            'apiSecret' => Craft::t('blitz', 'API Secret'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['apiKey'], 'required'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function purge(array $cacheIds)
    {

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
}