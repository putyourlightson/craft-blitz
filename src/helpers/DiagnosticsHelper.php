<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\ActiveQuery;
use craft\db\QueryAbortedException;
use craft\db\Table;
use craft\elements\GlobalSet;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use DateTime;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\records\CacheRecord;
use putyourlightson\blitz\records\CacheTagRecord;
use putyourlightson\blitz\records\DriverDataRecord;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementExpiryDateRecord;
use putyourlightson\blitz\records\ElementFieldCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;
use putyourlightson\blitz\records\ElementQueryFieldRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\IncludeRecord;
use putyourlightson\blitz\services\CacheRequestService;
use putyourlightson\blitzhints\BlitzHints;
use yii\db\ActiveRecordInterface;

/**
 * @since 4.10.0
 */
class DiagnosticsHelper
{
    /**
     * @const array
     */
    public const IS_CACHED_INCLUDE_CONDITION = ['like', 'uri', CacheRequestService::CACHED_INCLUDE_URI_PREFIX . '%', false];

    public static function getSiteId(): ?int
    {
        $siteId = null;
        $site = Craft::$app->getRequest()->getParam('site');
        if ($site) {
            $site = Craft::$app->getSites()->getSiteByHandle($site);
            $siteId = $site ? $site->id : null;
        }
        if (empty($siteId)) {
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        }

        return $siteId;
    }

    public static function getPagesCount(int $siteId): int
    {
        return CacheRecord::find()
            ->where(['siteId' => $siteId])
            ->andWhere(['not', self::IS_CACHED_INCLUDE_CONDITION])
            ->count();
    }

    public static function getIncludesCount(int $siteId): int
    {
        return CacheRecord::find()
            ->where(['siteId' => $siteId])
            ->andWhere(self::IS_CACHED_INCLUDE_CONDITION)
            ->count();
    }

    public static function getParamsCount(int $siteId): int
    {
        return count(self::getParams($siteId));
    }

    public static function getElementsCount(int $siteId): int
    {
        return ElementCacheRecord::find()
            ->innerJoinWith('cache')
            ->where(['siteId' => $siteId])
            ->count('DISTINCT [[elementId]]');
    }

    public static function getElementQueriesCount(int $siteId): int
    {
        return ElementQueryCacheRecord::find()
            ->innerJoinWith('cache')
            ->innerJoinWith('elementQuery')
            ->where(['siteId' => $siteId])
            ->count('DISTINCT [[queryId]]');
    }

    public static function getTagsCount(int $siteId): int
    {
        return CacheTagRecord::find()
            ->innerJoinWith('cache')
            ->where(['siteId' => $siteId])
            ->count('DISTINCT [[tag]]');
    }

    public static function getPage(): ?array
    {
        $pageId = Craft::$app->getRequest()->getRequiredParam('pageId');
        $page = CacheRecord::find()
            ->select(['id', 'uri'])
            ->where(['id' => $pageId])
            ->asArray()
            ->one();

        if ($page && $page['uri'] === '') {
            $page['uri'] = '/';
        }

        return $page;
    }

    public static function getInclude(): ?array
    {
        $includeId = Craft::$app->getRequest()->getRequiredParam('includeId');

        $index = self::getIncludesIndexColumnForSelect();

        return CacheRecord::find()
            ->select(['id', 'uri', $index . ' AS index'])
            ->where(['id' => $includeId])
            ->asArray()
            ->one();
    }

    public static function getElement(): ?ElementInterface
    {
        $siteId = Craft::$app->getRequest()->getRequiredParam('siteId');
        $elementId = Craft::$app->getRequest()->getRequiredParam('elementId');
        $elementType = Craft::$app->getRequest()->getRequiredParam('elementType');

        return $elementType::find()
            ->siteId($siteId)
            ->id($elementId)
            ->one();
    }

    public static function getElementQueryRecord(): ?ActiveRecordInterface
    {
        $queryId = Craft::$app->getRequest()->getRequiredParam('queryId');

        return ElementQueryRecord::find()
            ->where(['id' => $queryId])
            ->one();
    }

    public static function getElementTypes(int $siteId, ?int $cacheId = null): array
    {
        $condition = ['siteId' => $siteId];

        if ($cacheId) {
            $condition['cacheId'] = $cacheId;
        }

        return ElementCacheRecord::find()
            ->select(['type', 'count(DISTINCT [[elementId]]) as count'])
            ->innerJoinWith('cache')
            ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[elementId]]')
            ->where($condition)
            ->groupBy(['type'])
            ->orderBy(['count' => SORT_DESC, 'type' => SORT_ASC])
            ->asArray()
            ->all();
    }

    public static function getElementQueryTypes(int $siteId, ?int $cacheId = null): array
    {
        $condition = ['siteId' => $siteId];

        if ($cacheId) {
            $condition['cacheId'] = $cacheId;
        }

        return ElementQueryCacheRecord::find()
            ->select(['type', 'count(DISTINCT [[queryId]]) as count'])
            ->innerJoinWith('cache')
            ->innerJoinWith('elementQuery')
            ->where($condition)
            ->groupBy(['type'])
            ->orderBy(['count' => SORT_DESC, 'type' => SORT_ASC])
            ->asArray()
            ->all();
    }

    public static function getElementOfType(): Element
    {
        $elementType = Craft::$app->getRequest()->getParam('elementType');

        return new $elementType();
    }

    public static function getPagesQuery(int $siteId, ?int $elementId = null, ?int $queryId = null, ?string $tag = null, ?string $param = null): ActiveQuery
    {
        $query = self::getBasePagesQuery($siteId, $elementId, $queryId, $tag, $param);

        if ($elementId === null && $queryId === null && $tag === null) {
            $query->andWhere(['not', self::IS_CACHED_INCLUDE_CONDITION]);
        }

        return $query;
    }

    public static function getIncludesQuery(int $siteId): ActiveQuery
    {
        $index = self::getIncludesIndexColumnForSelect();

        return self::getBasePagesQuery($siteId)
            ->select(['caches.id', 'uri', $index . ' AS index', 'template', 'params', 'elementCount', 'elementQueryCount', 'expiryDate'])
            ->innerJoin([
                'indexes' => IncludeRecord::find()
                    ->where(['siteId' => $siteId]),
            ], $index . ' = [[indexes.index]]')
            ->andWhere(self::IS_CACHED_INCLUDE_CONDITION);
    }

    public static function getParams(int $siteId): array
    {
        $uris = CacheRecord::find()
            ->select(['uri'])
            ->where(['siteId' => $siteId])
            ->andWhere(['like', 'uri', '?'])
            ->andWhere(['not', self::IS_CACHED_INCLUDE_CONDITION])
            ->column();

        $queryStringParams = [];
        foreach ($uris as $uri) {
            $queryString = substr($uri, strpos($uri, '?') + 1);
            parse_str($queryString, $params);
            foreach ($params as $param => $value) {
                $queryStringParams[$param] = [
                    'param' => $param,
                    'count' => ($queryStringParams[$param]['count'] ?? 0) + 1,
                ];
            }
        }

        return $queryStringParams;
    }

    public static function getElementsQuery(int $siteId, string $elementType, ?int $cacheId = null): ActiveQuery
    {
        $condition = [
            CacheRecord::tableName() . '.siteId' => $siteId,
            'content.siteId' => $siteId,
            'type' => $elementType,
        ];

        if ($cacheId) {
            $condition['elementcaches.cacheId'] = $cacheId;
        }

        return ElementCacheRecord::find()
            ->from(['elementcaches' => ElementCacheRecord::tableName()])
            ->select(['elementcaches.elementId', 'elementexpirydates.expiryDate', 'count(*) as count', 'title'])
            ->innerJoinWith('cache')
            ->leftJoin(['elementexpirydates' => ElementExpiryDateRecord::tableName()], '[[elementexpirydates.elementId]] = [[elementcaches.elementId]]')
            ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[elementcaches.elementId]]')
            ->innerJoin(['content' => Table::CONTENT], '[[content.elementId]] = [[elementcaches.elementId]]')
            ->where($condition)
            ->groupBy(['elementcaches.elementId', 'elementexpirydates.expiryDate', 'title'])
            ->asArray();
    }

    public static function getElementQueriesQuery(int $siteId, string $elementType, ?int $cacheId = null): ActiveQuery
    {
        $condition = [
            'siteId' => $siteId,
            'type' => $elementType,
        ];

        if ($cacheId) {
            $condition['cacheId'] = $cacheId;
        }

        return ElementQueryCacheRecord::find()
            ->from(['elementquerycaches' => ElementQueryCacheRecord::tableName()])
            ->select([ElementQueryRecord::tableName() . '.id', 'index', 'params', 'count(*) as count'])
            ->innerJoinWith('cache')
            ->innerJoinWith('elementQuery')
            ->where($condition)
            ->groupBy([ElementQueryRecord::tableName() . '.id', 'params'])
            ->asArray();
    }

    public static function getElementsFromIds(int $siteId, string $elementType, array $elementIds): array
    {
        /** @var Element $elementType */
        return $elementType::find()
            ->siteId($siteId)
            ->status(null)
            ->id($elementIds)
            ->fixedOrder()
            ->indexBy('id')
            ->all();
    }

    public static function getPageTags(int $cacheId): array
    {
        $tags = CacheTagRecord::find()
            ->select(['tag'])
            ->where([
                'cacheId' => $cacheId,
            ])
            ->distinct()
            ->column();

        sort($tags);

        return $tags;
    }

    public static function getPageElementFields(int $cacheId, int $elementId): array
    {
        $fields = [];
        $fieldIds = ElementFieldCacheRecord::find()
            ->select(['fieldId'])
            ->where([
                'cacheId' => $cacheId,
                'elementId' => $elementId,
            ])
            ->distinct()
            ->column();

        // It’s safe to call this in a for loop, since the fields are memoized.
        foreach ($fieldIds as $fieldId) {
            $fields[] = Craft::$app->getFields()->getFieldById($fieldId);
        }

        ArrayHelper::multisort($fields, 'name');

        return $fields;
    }

    public static function getElementQueryFields(int $queryId): array
    {
        $fields = [];
        $fieldIds = ElementQueryFieldRecord::find()
            ->select(['fieldId'])
            ->where([
                'queryId' => $queryId,
            ])
            ->distinct()
            ->column();

        // It’s safe to call this in a for loop, since the fields are memoized.
        foreach ($fieldIds as $fieldId) {
            $fields[] = Craft::$app->getFields()->getFieldById($fieldId);
        }

        ArrayHelper::multisort($fields, 'name');

        return $fields;
    }

    public static function getElementQuerySql(string $elementQueryType, string $params): ?string
    {
        $params = Json::decodeIfJson($params);

        // Ensure JSON decode is successful
        if (!is_array($params)) {
            return null;
        }

        $elementQuery = RefreshCacheHelper::getElementQueryWithParams($elementQueryType, $params);

        if ($elementQuery === null) {
            return null;
        }

        try {
            $sql = $elementQuery
                ->select(['elementId' => 'elements.id'])
                ->createCommand()
                ->getRawSql();

            // Return raw SQL with line breaks replaced with spaces.
            return str_replace(["\r\n", "\r", "\n"], ' ', $sql);
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch (QueryAbortedException) {
            return null;
        }
    }

    public static function getTagsQuery(int $siteId): ActiveQuery
    {
        return CacheTagRecord::find()
            ->select(['tag', 'count(DISTINCT [[uri]]) as count'])
            ->innerJoinWith('cache')
            ->where(['siteId' => $siteId])
            ->groupBy(['tag'])
            ->orderBy(['count' => SORT_DESC, 'tag' => SORT_ASC])
            ->asArray();
    }

    public static function getDriverDataAction(string $action): ?string
    {
        /** @var DriverDataRecord|null $record */
        $record = DriverDataRecord::find()
            ->where(['driver' => 'diagnostics-utility'])
            ->one();

        if ($record === null) {
            return null;
        }

        $data = Json::decodeIfJson($record->data);

        if (!is_array($data)) {
            return null;
        }

        return $data[$action] ?? null;
    }

    public static function updateDriverDataAction(string $action): void
    {
        /** @var DriverDataRecord|null $record */
        $record = DriverDataRecord::find()
            ->where(['driver' => 'diagnostics-utility'])
            ->one();

        if ($record === null) {
            $record = new DriverDataRecord();
            $record->driver = 'diagnostics-utility';
        }

        $data = Json::decodeIfJson($record->data);

        if (!is_array($data)) {
            $data = [];
        }

        $data[$action] = Db::prepareDateForDb(new DateTime());
        $record->data = json_encode($data);
        $record->save();
    }

    public static function getTests(): array
    {
        $settings = Blitz::$plugin->settings;
        $tests = [];

        /**
         * Refresh cache when element saved unchanged test.
         */
        $pass = $settings->refreshCacheWhenElementSavedUnchanged === false;
        if ($pass) {
            $message = 'Blitz is configured not to refresh cached pages when an element is saved but unchanged.';
        } else {
            $message = 'Blitz is configured to refresh cached pages when an element is saved but remains unchanged.';
        }
        $tests[] = [
            'pass' => $pass,
            'message' => $message,
            'info' => 'With the <code>refreshCacheWhenElementSavedUnchanged</code> config setting disabled, cached pages are refreshed only when an element is saved and its content has changed. This is recommended and should only be enabled with good reason, as it can cause more refresh cache jobs to be created than necessary.',
        ];

        /**
         * Refresh cache when element saved not live test.
         */
        $pass = $settings->refreshCacheWhenElementSavedNotLive === false;
        if ($pass) {
            $message = 'Blitz is configured not to refresh cached pages when an element is saved but not live.';
        } else {
            $message = 'Blitz is configured to refresh cached pages when an element is saved but not live.';
        }
        $tests[] = [
            'pass' => $pass,
            'message' => $message,
            'info' => 'With the <code>refreshCacheWhenElementSavedNotLive</code> config setting disabled, cached pages are refreshed only when an element is saved and has a live status (live/active/enabled). This is recommended and should only be enabled with good reason, as it can cause more refresh cache jobs to be created than necessary.',
        ];

        /**
         * Generate transforms before page load test.
         */
        if (Craft::$app->getConfig()->getGeneral()->generateTransformsBeforePageLoad) {
            $pass = true;
            $message = 'Image transforms are configured to be generated before page load.';
        } else {
            $pass = false;
            $message = 'Image transforms are configured not to be generated before page load.';
        }
        $tests[] = [
            'pass' => $pass,
            'message' => $message,
            'info' => 'Blitz does not cache pages that contain asset transform generation URLs, as doing so can lead to cached pages that perform poorly. <a href="https://putyourlightson.com/plugins/blitz#the-site-is-not-cached-when-i-visit-it" target="_blank" class="go">Learn more</a>',
        ];

        /**
         * Global sets test.
         */
        $globalSetCount = GlobalSet::find()->count();
        if ($globalSetCount > 0) {
            $pass = $settings->refreshCacheAutomaticallyForGlobals === false;
            if ($pass) {
                $message = 'One or more <a href="' . UrlHelper::cpUrl('globals') . '">globals</a> exist and <code>refreshCacheAutomaticallyForGlobals</code> is disabled.';
            } else {
                $message = 'One or more <a href="' . UrlHelper::cpUrl('globals') . '">globals</a> exist and <code>refreshCacheAutomaticallyForGlobals</code> is enabled.';
            }
        } else {
            $pass = true;
            $message = 'No globals exist.';
        }
        $tests[] = [
            'pass' => $pass,
            'message' => $message,
            'info' => '<a href="https://craftcms.com/docs/4.x/globals.html" target="">Globals</a> should be avoided, since they are preloaded on every page in your site, unless the <code>refreshCacheAutomaticallyForGlobals</code> config setting is disabled. <a href="https://putyourlightson.com/plugins/blitz#2-avoid-using-globals" target="_blank" class="go">Learn more</a>',
        ];

        /**
         * Web alias test.
         */
        if (Craft::$app->getRequest()->isWebAliasSetDynamically) {
            $failedSites = 0;
            foreach (Craft::$app->getSites()->getAllSites() as $site) {
                if (str_contains($site->getBaseUrl(false), '@web')) {
                    $failedSites++;
                }
            }

            $pass = $failedSites === 0;
            if ($pass) {
                $message = 'The <a href="https://craftcms.com/docs/4.x/config/#aliases" target="_blank"><code>@web</code></a> alias is not used in the base URL of any <a href="' . UrlHelper::cpUrl('settings/sites') . '">sites</a>.';
            } else {
                $message = '<a href="https://craftcms.com/docs/4.x/config/#aliases" target="_blank"><code>@web</code></a> alias is used in {{ failedSites }} <a href="' . UrlHelper::cpUrl('settings/sites') . '">' . Craft::t('blitz', '{num, plural, =1{site} other{sites}}', ['num' => $failedSites]) . '</a> and is not explicitly defined.';
            }
        } else {
            $pass = true;
            $message = 'The <a href="https://craftcms.com/docs/4.x/config/#aliases" target="_blank"><code>@web</code></a> alias is explicitly defined.';
        }
        $tests[] = [
            'pass' => $pass,
            'message' => $message,
            'info' => 'Explicitly defining the <a href="https://craftcms.com/docs/4.x/config/#aliases" target="_blank"><code>@web</code></a> alias is important for ensuring that URLs work correctly when the cache is generated via console requests. <a href="https://putyourlightson.com/plugins/blitz#the-site-is-not-cached-when-using-console-commands" target="_blank" class="go">Learn more</a>',
        ];

        /**
         * Run queue automatically test.
         */
        $pass = Craft::$app->getConfig()->getGeneral()->runQueueAutomatically === false;
        if ($pass) {
            $message = 'Queue jobs are configured not to run automatically via web requests.';
        } else {
            $message = 'Queue jobs are configured to run automatically via web requests.';
        }
        $tests[] = [
            'pass' => $pass,
            'message' => $message,
            'info' => 'Running queue jobs via web requests can negatively impact the performance of a site and cause queue jobs to stall. <a href="https://putyourlightson.com/plugins/blitz#the-refresh-cache-queue-job-is-stalling" target="_blank" class="go">Learn more</a>',
        ];

        /**
         * Blitz Hints test.
         */
        if (Blitz::$plugin->settings->hintsEnabled) {
            $hintsCount = BlitzHints::getInstance()->hints->getTotalWithoutRouteVariables();
            $pass = $hintsCount === 0;
            if ($pass) {
                $message = 'The <a href="' . UrlHelper::cpUrl('utilities/blitz-hints') . '">Blitz Hints</a> utility is not reporting any eager-loading opportunities.';
            } else {
                $message = 'The <a href="' . UrlHelper::cpUrl('utilities/blitz-hints') . '">Blitz Hints</a> utility is reporting ' . $hintsCount . ' eager-loading ' . Craft::t('blitz', '{num, plural, =1{opportunity} other{opportunities}}', ['num' => $hintsCount]) . '.';
            }
        } else {
            $pass = true;
            $message = 'The Blitz Hints utility is disabled.';
        }
        $tests[] = [
            'pass' => $pass,
            'message' => $message,
            'info' => 'Eager-loading elements is highly recommended. The Blitz Hints utility lists opportunities for eager-loading elements including the field name, the template and the line number. <a href="https://putyourlightson.com/plugins/blitz#hints-utility" target="_blank" class="go">Learn more</a>',
        ];

        /**
         * Async Queue plugin test.
         */
        $pass = Craft::$app->getPlugins()->getPlugin('async-queue') === null;
        if ($pass) {
            $message = 'The <a href="https://plugins.craftcms.com/async-queue" target="_blank">Async Queue</a> plugin is not installed or enabled.';
        } else {
            $message = 'The <a href="https://plugins.craftcms.com/async-queue" target="_blank">Async Queue</a> plugin is installed and enabled.';
        }
        $tests[] = [
            'pass' => $pass,
            'message' => $message,
            'info' => 'The <a href="https://plugins.craftcms.com/async-queue" target="_blank">Async Queue</a> plugin can be unreliable when used in some environments and cause queue jobs to stall. <a href="https://putyourlightson.com/plugins/blitz#the-refresh-cache-queue-job-is-stalling" target="_blank" class="go">Learn more</a>',
        ];

        $refreshExpired = self::getDriverDataAction('refresh-expired-cli');
        $yesterday = Db::prepareDateForDb(DateTimeHelper::toDateTime('-24 hours'));
        $pass = $refreshExpired !== null && $refreshExpired > $yesterday;
        if ($pass) {
            $message = 'The <code>blitz/cache/refresh-expired</code> console command has been executed within the past 24 hours.';
        } else {
            $message = 'The <code>blitz/cache/refresh-expired</code> console command has not been executed within the past 24 hours.';
        }
        $tests[] = [
            'pass' => $pass,
            'message' => $message,
            'info' => 'The <code>blitz/cache/refresh-expired</code> console command not having been executed within the past 24 hours can indicate that a scheduled cron job should be set up to refresh expired cache at a recurring interval. (You may have to wait for the cron job to run after an update, for this warning to disappear.) <a href="https://putyourlightson.com/plugins/blitz#cron-jobs" target="_blank" class="go">Learn more</a>',
        ];

        ArrayHelper::multisort($tests, 'pass');

        return $tests;
    }

    public static function toDateTime(string $value): DateTime|null
    {
        return DateTimeHelper::toDateTime($value);
    }

    private static function getBasePagesQuery(int $siteId, ?int $elementId = null, ?int $queryId = null, ?string $tag = null, ?string $param = null): ActiveQuery
    {
        $query = CacheRecord::find()
            ->from(['caches' => CacheRecord::tableName()])
            ->select(['id', 'uri', 'elementCount', 'elementQueryCount', 'tagCount', 'expiryDate'])
            ->where(['caches.siteId' => $siteId])
            ->asArray();

        if ($elementId !== null) {
            $query
                ->innerJoin([
                    'elements' => ElementCacheRecord::find()
                        ->select(['cacheId', 'elementId', 'count(*) as elementCount'])
                        ->groupBy(['cacheId', 'elementId']),
                ], 'id = [[elements.cacheId]]')
                ->andWhere(['elementId' => $elementId]);
        } else {
            $query->leftJoin([
                'elements' => ElementCacheRecord::find()
                    ->select(['cacheId', 'count(*) as elementCount'])
                    ->groupBy(['cacheId']),
            ], 'id = [[elements.cacheId]]');
        }

        if ($queryId !== null) {
            $query
                ->innerJoin([
                    'elementquerycaches' => ElementQueryCacheRecord::find()
                        ->select(['cacheId', 'queryId', 'count(*) as elementQueryCount'])
                        ->groupBy(['cacheId', 'queryId']),
                ], 'id = [[elementquerycaches.cacheId]]')
                ->andWhere(['queryId' => $queryId]);
        } else {
            $query
                ->leftJoin([
                    'elementquerycaches' => ElementQueryCacheRecord::find()
                        ->select(['cacheId', 'count(*) as elementQueryCount'])
                        ->groupBy(['cacheId']),
                ], 'id = [[elementquerycaches.cacheId]]');
        }

        if ($tag !== null) {
            $query
                ->innerJoin([
                    'cachetags' => CacheTagRecord::find()
                        ->select(['cacheId', 'tag', 'count(*) as tagCount'])
                        ->groupBy(['cacheId', 'tag']),
                ], '[[caches.id]] = [[cachetags.cacheId]]')
                ->andWhere(['tag' => $tag]);
        } else {
            $query
                ->leftJoin([
                    'cachetags' => CacheTagRecord::find()
                        ->select(['cacheId', 'count(*) as tagCount'])
                        ->groupBy(['cacheId']),
                ], '[[caches.id]] = [[cachetags.cacheId]]');
        }

        if ($param !== null) {
            $query->andWhere(['like', 'uri', $param]);
        }

        return $query;
    }

    private static function getIncludesIndexColumnForSelect(): string
    {
        // Cast the string to a BIGINT for Postgres.
        // https://github.com/putyourlightson/craft-blitz/issues/653
        $index = 'SUBSTRING([[uri]], 49)';
        if (Craft::$app->getDb()->getIsPgsql()) {
            $index = 'CAST(' . $index . ' AS BIGINT)';
        }

        return $index;
    }
}
