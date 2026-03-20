<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\purgers;

use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\db\Table;
use craft\errors\SiteNotFoundException;
use craft\helpers\App;
use craft\helpers\Db;
use GuzzleHttp\Exception\RequestException;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\SiteUriHelper;
use yii\log\Logger;

/**
 * @property-read null|string $settingsHtml
 */
class CloudflarePurger extends BaseCachePurger
{
    /**
     * @const The API endpoint URL.
     * https://developers.cloudflare.com/api/resources/cache/
     */
    public const API_ENDPOINT = 'https://api.cloudflare.com/client/v4/';

    /**
     * @const The API URL limit.
     * https://developers.cloudflare.com/cache/how-to/purge-cache/#single-file-purge-limits
     */
    public const API_URL_LIMIT = 100;

    /**
     * @const The max number of API attempts, in case of rate limiting.
     */
    public const MAX_ATTEMPTS = 3;

    /**
     * @var string The API authentication method.
     */
    public string $authenticationMethod = 'apiToken';

    /**
     * @var string|null The API token.
     */
    public ?string $apiToken = null;

    /**
     * @var string|null The API key.
     */
    public ?string $apiKey = null;

    /**
     * @var string|null The email address to use.
     */
    public ?string $email = null;

    /**
     * @var array The zone IDs to purge.
     */
    public array $zoneIds = [];

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Cloudflare Purger');
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
    public function purgeSite(int $siteId, callable $setProgressHandler = null, bool $queue = true): void
    {
        $this->sendRequest('delete', 'purge_cache', $siteId, [
            'purge_everything' => true,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function purgeUrisWithProgress(array $siteUris, callable $setProgressHandler = null): void
    {
        $count = 0;
        $total = count($siteUris);
        $label = 'Purging {total} pages';

        if (is_callable($setProgressHandler)) {
            $progressLabel = Craft::t('blitz', $label, ['total' => $total]);
            call_user_func($setProgressHandler, $count, $total, $progressLabel);
        }

        $groupedSiteUris = SiteUriHelper::getSiteUrisGroupedBySite($siteUris);

        foreach ($groupedSiteUris as $siteId => $siteUriGroup) {
            $this->sendRequest('delete', 'purge_cache', $siteId, [
                'files' => SiteUriHelper::getUrlsFromSiteUris($siteUriGroup),
            ]);

            $count = $count + count($siteUriGroup);

            if (is_callable($setProgressHandler)) {
                $progressLabel = Craft::t('blitz', $label, ['total' => $total]);
                call_user_func($setProgressHandler, $count, $total, $progressLabel);
            }
        }
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
                } catch (SiteNotFoundException $exception) {
                    Blitz::$plugin->log($exception->getMessage(), [], Logger::LEVEL_ERROR);

                    continue;
                }

                $response = $this->sendRequest('get', '', $site->id);

                if ($response === false) {
                    $error = Craft::t('blitz', 'Error connecting to Cloudflare using zone ID for “{site}”.', ['site' => $site->name]);

                    $this->addError('zoneIds', $error);
                }
            }
        }

        return !$this->hasErrors();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('blitz/_drivers/purgers/cloudflare/settings', [
            'purger' => $this,
            'readOnly' => !Craft::$app->getConfig()->getGeneral()->allowAdminChanges,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function defineBehaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => [
                    'apiToken',
                    'apiKey',
                    'email',
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [
                ['apiToken'], 'required', 'when' => function(CloudflarePurger $purger) {
                    return $purger->authenticationMethod == 'apiToken';
                },
            ],
            [
                ['apiKey', 'email'], 'required', 'when' => function(CloudflarePurger $purger) {
                    return $purger->authenticationMethod == 'apiKey';
                },
            ],
            [
                ['email'], 'email', 'when' => function(CloudflarePurger $purger) {
                    return $purger->authenticationMethod == 'apiKey';
                },
            ],
        ];
    }

    /**
     * Sends a request to the API.
     */
    private function sendRequest(string $method, string $action, int $siteId, array $params = []): bool
    {
        $response = true;

        $headers = ['Content-Type' => 'application/json'];

        if ($this->authenticationMethod == 'apiKey') {
            $headers['X-Auth-Key'] = App::parseEnv($this->apiKey);
            $headers['X-Auth-Email'] = App::parseEnv($this->email);
        } else {
            $headers['Authorization'] = 'Bearer ' . App::parseEnv($this->apiToken);
        }

        $client = Craft::createGuzzleClient([
            'base_uri' => self::API_ENDPOINT,
            'headers' => $headers,
        ]);

        $siteUid = Db::uidById(Table::SITES, $siteId);

        if ($siteUid === null) {
            return false;
        }

        if (empty($this->zoneIds[$siteUid]) || empty($this->zoneIds[$siteUid]['zoneId'])) {
            return false;
        }

        $uri = 'zones/' . App::parseEnv($this->zoneIds[$siteUid]['zoneId']) . '/' . $action;
        $optionSets = [];

        if (!empty($params['files'])) {
            $files = $params['files'];
            $batches = array_chunk($files, self::API_URL_LIMIT);

            foreach ($batches as $batch) {
                $optionSets[] = [
                    'json' => ['files' => $batch],
                ];
            }
        } else {
            $optionSets[] = [
                'json' => $params,
            ];
        }

        foreach ($optionSets as $options) {
            $attempts = 0;
            while ($attempts < self::MAX_ATTEMPTS) {
                try {
                    $client->request($method, $uri, $options);
                    break; // Success, exit retry loop
                } catch (RequestException $exception) {
                    $statusCode = $exception->getResponse()?->getStatusCode();
                    $attempts++;

                    // If 429 Too Many Requests, wait and retry
                    if ($statusCode === 429 && $attempts < self::MAX_ATTEMPTS) {
                        sleep(1);
                        continue;
                    }

                    // For other errors, log and mark as failed
                    $response = false;
                    preg_match('/^(.*?)\R/', $exception->getMessage(), $matches);

                    if (!empty($matches[1])) {
                        Blitz::$plugin->log(trim($matches[1], ':'), [], Logger::LEVEL_ERROR);
                    }
                    break;
                }
            }
        }

        return $response;
    }
}
