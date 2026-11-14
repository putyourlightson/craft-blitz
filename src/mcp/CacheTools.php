<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\mcp;

use Craft;
use craft\mcp\attributes\RequiresPermission;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\storage\FileStorage;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\utilities\CacheUtility;

/**
 * MCP tools for Blitz.
 */
class CacheTools
{
    #[McpTool(
        name: 'blitz.status',
        description: 'Returns Blitz cache status, configured driver types, available actions and per-site file cache counts.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    #[RequiresPermission('utility:blitz-cache')]
    public function status(): array
    {
        $plugin = Blitz::$plugin;
        $storage = $plugin->cacheStorage;
        $actions = array_values(array_filter(
            CacheUtility::getActions(),
            fn(array $action) => Craft::$app->getUser()->checkPermission('blitz:' . $action['id']),
        ));

        return [
            'cachingEnabled' => $plugin->settings->cachingEnabled,
            'refreshMode' => $plugin->settings->refreshMode,
            'cacheStorage' => $storage::class,
            'cacheGenerator' => $plugin->cacheGenerator::class,
            'cachePurger' => $plugin->cachePurger::class,
            'deployer' => $plugin->deployer::class,
            'actions' => $actions,
            'sites' => $storage instanceof FileStorage && $storage->countCachedFiles ? $storage->getSitePageCount() : null,
        ];
    }

    #[McpTool(
        name: 'blitz.tags',
        description: 'Returns cache tags available for tagged cache operations.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    #[RequiresPermission('blitz:refresh-tagged')]
    public function tags(): array
    {
        return ['tags' => Blitz::$plugin->cacheTags->getAllTags()];
    }

    #[McpTool(
        name: 'blitz.clear',
        description: 'Deletes cached pages.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:clear')]
    public function clear(): array
    {
        Blitz::$plugin->clearCache->clearAll();

        return $this->success('clear');
    }

    #[McpTool(
        name: 'blitz.clear-site',
        description: 'Deletes cached pages in the provided site.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:clear')]
    public function clearSite(#[Schema(minimum: 1)] int $siteId): array
    {
        $this->requireSite($siteId);
        Blitz::$plugin->clearCache->clearUris(SiteUriHelper::getSiteUrisForSiteWithCustomSiteUris($siteId));

        return $this->success('clear-site');
    }

    /**
     * @param string[] $urls
     */
    #[McpTool(
        name: 'blitz.clear-urls',
        description: 'Deletes cached pages matching the provided URLs. Supports the * wildcard.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:clear')]
    public function clearUrls(#[Schema(items: ['type' => 'string', 'minLength' => 1], minItems: 1)] array $urls): array
    {
        $this->requireValues($urls);
        Blitz::$plugin->clearCache->clearUris(SiteUriHelper::getSiteUrisFromUrls($urls));

        return $this->success('clear-urls');
    }

    /**
     * @param string[] $tags
     */
    #[McpTool(
        name: 'blitz.clear-tagged',
        description: 'Deletes cached pages with the provided tags.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:clear')]
    public function clearTagged(#[Schema(items: ['type' => 'string', 'minLength' => 1], minItems: 1)] array $tags): array
    {
        $this->requireValues($tags);
        Blitz::$plugin->clearCache->clearUris(SiteUriHelper::getSiteUrisFromTags($tags));

        return $this->success('clear-tagged');
    }

    #[McpTool(
        name: 'blitz.generate',
        description: 'Queues generation of cacheable pages.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:generate')]
    public function generate(): array
    {
        $this->requireCachingEnabled();
        Blitz::$plugin->cacheGenerator->generateAll();

        return $this->success('generate');
    }

    #[McpTool(
        name: 'blitz.generate-site',
        description: 'Queues generation of cacheable pages in the provided site.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:generate')]
    public function generateSite(#[Schema(minimum: 1)] int $siteId): array
    {
        $this->requireCachingEnabled();
        $this->requireSite($siteId);
        Blitz::$plugin->cacheGenerator->generateUris(SiteUriHelper::getSiteUrisForSiteWithCustomSiteUris($siteId));

        return $this->success('generate-site');
    }

    /**
     * @param string[] $urls
     */
    #[McpTool(
        name: 'blitz.generate-urls',
        description: 'Queues generation of cacheable pages matching the provided URLs. Supports the * wildcard.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:generate')]
    public function generateUrls(#[Schema(items: ['type' => 'string', 'minLength' => 1], minItems: 1)] array $urls): array
    {
        $this->requireCachingEnabled();
        $this->requireValues($urls);
        Blitz::$plugin->cacheGenerator->generateUris(SiteUriHelper::getSiteUrisFromUrls($urls));

        return $this->success('generate-urls');
    }

    /**
     * @param string[] $tags
     */
    #[McpTool(
        name: 'blitz.generate-tagged',
        description: 'Queues generation of cacheable pages with the provided tags.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:generate')]
    public function generateTagged(#[Schema(items: ['type' => 'string', 'minLength' => 1], minItems: 1)] array $tags): array
    {
        $this->requireCachingEnabled();
        $this->requireValues($tags);
        Blitz::$plugin->cacheGenerator->generateUris(SiteUriHelper::getSiteUrisFromTags($tags));

        return $this->success('generate-tagged');
    }

    #[McpTool(
        name: 'blitz.purge',
        description: 'Queues deletion of cached pages from the reverse proxy.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:purge')]
    public function purge(): array
    {
        if (Blitz::$plugin->cachePurger->isDummy) {
            throw new ToolCallException('Cache purging is disabled.');
        }
        Blitz::$plugin->cachePurger->purgeAll();

        return $this->success('purge');
    }

    #[McpTool(
        name: 'blitz.purge-site',
        description: 'Queues deletion of cached pages from the reverse proxy in the provided site.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:purge')]
    public function purgeSite(#[Schema(minimum: 1)] int $siteId): array
    {
        if (Blitz::$plugin->cachePurger->isDummy) {
            throw new ToolCallException('Cache purging is disabled.');
        }
        $this->requireSite($siteId);
        Blitz::$plugin->cachePurger->purgeUris(SiteUriHelper::getSiteUrisForSiteWithCustomSiteUris($siteId));

        return $this->success('purge-site');
    }

    /**
     * @param string[] $urls
     */
    #[McpTool(
        name: 'blitz.purge-urls',
        description: 'Queues deletion of cached pages from the reverse proxy matching the provided URLs. Supports the * wildcard.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:purge')]
    public function purgeUrls(#[Schema(items: ['type' => 'string', 'minLength' => 1], minItems: 1)] array $urls): array
    {
        if (Blitz::$plugin->cachePurger->isDummy) {
            throw new ToolCallException('Cache purging is disabled.');
        }
        $this->requireValues($urls);
        Blitz::$plugin->cachePurger->purgeUris(SiteUriHelper::getSiteUrisFromUrls($urls));

        return $this->success('purge-urls');
    }

    /**
     * @param string[] $tags
     */
    #[McpTool(
        name: 'blitz.purge-tagged',
        description: 'Queues deletion of cached pages from the reverse proxy with the provided tags.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:purge')]
    public function purgeTagged(#[Schema(items: ['type' => 'string', 'minLength' => 1], minItems: 1)] array $tags): array
    {
        if (Blitz::$plugin->cachePurger->isDummy) {
            throw new ToolCallException('Cache purging is disabled.');
        }
        $this->requireValues($tags);
        Blitz::$plugin->cachePurger->purgeUris(SiteUriHelper::getSiteUrisFromTags($tags));

        return $this->success('purge-tagged');
    }

    #[McpTool(
        name: 'blitz.flush',
        description: 'Deletes all cache records from the database.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:flush')]
    public function flush(): array
    {
        Blitz::$plugin->flushCache->flushAll();

        return $this->success('flush');
    }

    #[McpTool(
        name: 'blitz.flush-site',
        description: 'Deletes all cache records for the provided site from the database. Cached files are retained.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:flush')]
    public function flushSite(#[Schema(minimum: 1)] int $siteId): array
    {
        $this->requireSite($siteId);
        Blitz::$plugin->flushCache->flushSite($siteId);

        return $this->success('flush-site');
    }

    /**
     * @param string[] $urls
     */
    #[McpTool(
        name: 'blitz.flush-urls',
        description: 'Deletes cache records matching the provided URLs from the database. Supports the * wildcard. Cached files are retained.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:flush')]
    public function flushUrls(#[Schema(items: ['type' => 'string', 'minLength' => 1], minItems: 1)] array $urls): array
    {
        $this->requireValues($urls);
        Blitz::$plugin->flushCache->flushUris(SiteUriHelper::getSiteUrisFromUrls($urls));

        return $this->success('flush-urls');
    }

    #[McpTool(
        name: 'blitz.deploy',
        description: 'Queues deployment of all cached files to the configured remote location.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:deploy')]
    public function deploy(): array
    {
        $this->requireCachingEnabled();
        if (Blitz::$plugin->deployer->isDummy) {
            throw new ToolCallException('Deploying is disabled.');
        }
        Blitz::$plugin->deployer->deployAll();

        return $this->success('deploy');
    }

    #[McpTool(
        name: 'blitz.refresh',
        description: 'Refreshes all pages according to the configured Refresh Mode. Jobs are queued.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:refresh')]
    public function refresh(): array
    {
        Blitz::$plugin->refreshCache->refreshAll();
        Blitz::$plugin->refreshCache->refresh();

        return $this->success('refresh');
    }

    #[McpTool(
        name: 'blitz.refresh-expired',
        description: 'Refreshes expired cache and queues pending refresh work.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:refresh-expired')]
    public function refreshExpired(): array
    {
        $refreshCache = Blitz::$plugin->refreshCache;
        $forceGenerate = $refreshCache->forceGenerate;

        try {
            $refreshCache->refreshExpiredCache();
            $refreshCache->refresh();
        } finally {
            $refreshCache->forceGenerate = $forceGenerate;
        }

        return $this->success('refresh-expired');
    }

    #[McpTool(
        name: 'blitz.refresh-site',
        description: 'Refreshes all pages in the provided site according to the Refresh Mode. Jobs are queued.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:refresh-site')]
    public function refreshSite(#[Schema(minimum: 1)] int $siteId): array
    {
        $this->requireSite($siteId);
        Blitz::$plugin->refreshCache->refreshSite($siteId);
        Blitz::$plugin->refreshCache->refresh();

        return $this->success('refresh-site');
    }

    /**
     * @param string[] $urls
     */
    #[McpTool(
        name: 'blitz.refresh-urls',
        description: 'Refreshes cached URLs according to the Refresh Mode. Supports the * wildcard. Jobs are queued.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:refresh-urls')]
    public function refreshUrls(#[Schema(items: ['type' => 'string', 'minLength' => 1], minItems: 1)] array $urls): array
    {
        $this->requireValues($urls);
        Blitz::$plugin->refreshCache->refreshCachedUrls($urls);
        Blitz::$plugin->refreshCache->refresh();

        return $this->success('refresh-urls');
    }

    /**
     * @param string[] $tags
     */
    #[McpTool(
        name: 'blitz.refresh-tagged',
        description: 'Refreshes cached pages with the provided tags according to the Refresh Mode. Jobs are queued.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:refresh-tagged')]
    public function refreshTagged(#[Schema(items: ['type' => 'string', 'minLength' => 1], minItems: 1)] array $tags): array
    {
        $this->requireValues($tags);
        Blitz::$plugin->refreshCache->refreshCacheTags($tags);
        Blitz::$plugin->refreshCache->refresh();

        return $this->success('refresh-tagged');
    }

    #[McpTool(
        name: 'blitz.refresh-expired-elements',
        description: 'Queues refreshes of cached pages tracking expired elements.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:refresh-expired')]
    public function refreshExpiredElements(): array
    {
        Blitz::$plugin->refreshCache->refreshExpiredElements();
        Blitz::$plugin->refreshCache->refresh();

        return $this->success('refresh-expired-elements');
    }

    #[McpTool(
        name: 'blitz.generate-expiry-dates',
        description: 'Generates and stores entry expiry dates for cache invalidation.',
        annotations: new ToolAnnotations(destructiveHint: true),
    )]
    #[RequiresPermission('blitz:generate')]
    public function generateExpiryDates(): array
    {
        Blitz::$plugin->refreshCache->generateExpiryDates();

        return $this->success('generate-expiry-dates');
    }

    private function requireCachingEnabled(): void
    {
        if (!Blitz::$plugin->settings->cachingEnabled) {
            throw new ToolCallException('Blitz caching is disabled.');
        }
    }

    private function requireSite(int $siteId): void
    {
        if (Craft::$app->getSites()->getSiteById($siteId) === null) {
            throw new ToolCallException('A valid site ID must be provided.');
        }
    }

    private function requireValues(array $values): void
    {
        if ($values === []) {
            throw new ToolCallException('At least one value must be provided.');
        }

        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new ToolCallException('Values must be non-empty strings.');
            }
        }
    }

    /**
     * Reports submission rather than completion, since drivers may queue jobs.
     *
     * @return array{success: bool, message: string}
     */
    private function success(string $action): array
    {
        $message = Craft::t('blitz', 'Blitz action “{action}” submitted. Any queued jobs must be processed by the configured Blitz queue.', ['action' => $action]);
        Blitz::$plugin->log($message . ' [via MCP by “{username}”]');

        return ['success' => true, 'message' => $message];
    }
}
