<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz;

use Craft;
use craft\base\Plugin;
use craft\elements\db\ElementQuery;
use craft\events\CancelableEvent;
use craft\events\ElementEvent;
use craft\events\MoveElementEvent;
use craft\events\PopulateElementEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\TemplateEvent;
use craft\models\Site;
use craft\services\Elements;
use craft\services\Structures;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\web\Response;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\services\CacheService;
use putyourlightson\blitz\services\FileService;
use putyourlightson\blitz\utilities\CacheUtility;
use putyourlightson\blitz\variables\BlitzVariable;
use yii\base\Event;

/**
 *
 * @property CacheService $cache
 * @property FileService $file
 *
 * @method SettingsModel getSettings()
 */
class Blitz extends Plugin
{
    /**
     * @var Blitz
     */
    public static $plugin;

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        self::$plugin = $this;

        // Register services as components
        $this->setComponents([
            'cache' => CacheService::class,
            'file' => FileService::class,
        ]);

        // Register variable
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            /** @var CraftVariable $variable */
            $variable = $event->sender;
            $variable->set('blitz', BlitzVariable::class);
        });

        // Process request
        $this->processRequest();
    }

    /**
     * Processes the request
     */
    public function processRequest()
    {
        $request = Craft::$app->getRequest();

        // Cacheable request
        if ($this->cache->getIsCacheableRequest()) {
            $site = Craft::$app->getSites()->getCurrentSite();

            // Get URI from absolute URL
            $uri = $this->_getUri($site, $request->getAbsoluteUrl());

            if ($this->cache->getIsCacheableUri($site->id, $uri)) {
                // If cached version exists then output it (assuming this has not already been done server-side)
                $filePath = $this->file->getFilePath($site->id, $uri);
                if (is_file($filePath)) {
                    $this->file->outputFile($filePath);
                }

                $this->_registerCacheableRequestEvents($site->id, $uri);
            }
        }

        // CP request
        if ($request->getIsCpRequest()) {
            $this->_registerElementEvents();

            $this->_registerUtilities();

            if (Craft::$app->getEdition() === Craft::Pro) {
                $this->_registerUserPermissions();
            }
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): SettingsModel
    {
        return new SettingsModel();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('blitz/_settings', [
            'settings' => $this->getSettings(),
            'config' => Craft::$app->getConfig()->getConfigFromFile('blitz'),
        ]);
    }

    // Private Methods
    // =========================================================================

    /**
     * Gets the URI
     *
     * @param Site $site
     * @param string $uri
     *
     * @return string
     */
    private function _getUri(Site $site, string $uri): string
    {
        // Remove the query string if unique query strings should be cached as the same page
        if ($this->getSettings()->queryStringCaching == 2) {
            $uri = preg_replace('/\?.*/', '', $uri);
        }

        // Remove site base URL
        $baseUrl = trim(Craft::getAlias($site->baseUrl), '/');
        $uri = str_replace($baseUrl, '', $uri);

        // Trim slashes from the beginning and end of the URI
        $uri = trim($uri, '/');

        return $uri;
    }

    /**
     * Registers element events
     */
    private function _registerElementEvents()
    {
        // Invalidate elements
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(ElementEvent $event) {
                $this->cache->invalidateElement($event->element);
            }
        );
        Event::on(Structures::class, Structures::EVENT_AFTER_MOVE_ELEMENT,
            function(MoveElementEvent $event) {
                $this->cache->invalidateElement($event->element);
            }
        );
        Event::on(Elements::class, Elements::EVENT_BEFORE_DELETE_ELEMENT,
            function(ElementEvent $event) {
                $this->cache->invalidateElement($event->element);
            }
        );

        // Invalidate cache after response is prepared (set append to false we get in early)
        Event::on(Response::class, Response::EVENT_AFTER_PREPARE,
            function() {
                $this->cache->invalidateCache();
            },
            null,
            false
        );
    }

    /**
     * Registers cacheable request events
     *
     * @param int $siteId
     * @param string $uri
     */
    private function _registerCacheableRequestEvents(int $siteId, string $uri)
    {
        // We'll need to check if the response is ok again inside the event functions as it may change during the request
        $response = Craft::$app->getResponse();

        // Register element populate event
        Event::on(ElementQuery::class, ElementQuery::EVENT_AFTER_POPULATE_ELEMENT,
            function(PopulateElementEvent $event) use ($response) {
                if ($response->getIsOk()) {
                    $this->cache->addElementCache($event->element);
                }
            }
        );

        // Register element query prepare event
        Event::on(ElementQuery::class, ElementQuery::EVENT_AFTER_PREPARE,
            function(CancelableEvent $event) use ($response) {
                if ($response->getIsOk()) {
                    /** @var ElementQuery $elementQuery */
                    $elementQuery = $event->sender;
                    $this->cache->addElementQueryCache($elementQuery);
                }
            }
        );

        // Register template page render events
        Event::on(View::class, View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            function() use ($siteId, $uri) {
                $this->cache->clearCacheRecords($siteId, $uri);
            }
        );
        Event::on(View::class, View::EVENT_AFTER_RENDER_PAGE_TEMPLATE,
            function(TemplateEvent $event) use ($response, $siteId, $uri) {
                if ($response->getIsOk()) {
                    $this->cache->cacheOutput($event->output, $siteId, $uri);
                }
            }
        );
    }

    /**
     * Registers utilities
     */
    private function _registerUtilities()
    {
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITY_TYPES,
            function(RegisterComponentTypesEvent $event) {
                if (Craft::$app->getUser()->checkPermission('blitz:cache-utility')) {
                    $event->types[] = CacheUtility::class;
                }
            }
        );
    }

    /**
     * Registers user permissions
     */
    private function _registerUserPermissions()
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions['Blitz'] = [
                    'blitz:cache-utility' => ['label' => Craft::t('blitz', 'Access cache utility')],
                ];
            }
        );
    }
}