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
    public $email;

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