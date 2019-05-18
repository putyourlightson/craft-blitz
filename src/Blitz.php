<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz;

use Craft;
use craft\base\Plugin;
use craft\console\controllers\ResaveController;
use craft\elements\db\ElementQuery;
use craft\events\CancelableEvent;
use craft\events\ElementEvent;
use craft\events\MoveElementEvent;
use craft\events\PluginEvent;
use craft\events\PopulateElementEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\TemplateEvent;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\Gc;
use craft\services\Plugins;
use craft\services\Structures;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use putyourlightson\blitz\drivers\storage\BaseCacheStorage;
use putyourlightson\blitz\helpers\CachePurgerHelper;
use putyourlightson\blitz\helpers\RequestHelper;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\drivers\purgers\BaseCachePurger;
use putyourlightson\blitz\services\CacheTagsService;
use putyourlightson\blitz\services\FlushCacheService;
use putyourlightson\blitz\services\GenerateCacheService;
use putyourlightson\blitz\services\ClearCacheService;
use putyourlightson\blitz\services\OutputCacheService;
use putyourlightson\blitz\services\WarmCacheService;
use putyourlightson\blitz\services\RefreshCacheService;
use putyourlightson\blitz\utilities\CacheUtility;
use putyourlightson\blitz\variables\BlitzVariable;
use yii\base\Event;

/**
 *
 * @property CacheTagsService $cacheTags
 * @property ClearCacheService $clearCache
 * @property FlushCacheService $flushCache
 * @property GenerateCacheService $generateCache
 * @property OutputCacheService $outputCache
 * @property RefreshCacheService $refreshCache
 * @property WarmCacheService $warmCache
 * @property BaseCacheStorage $cacheStorage
 * @property BaseCachePurger $cachePurger
 * @property SettingsModel $settings
 * @property mixed $settingsResponse
 * @property array $cpRoutes
 *
 * @method SettingsModel getSettings()
 */
class Blitz extends Plugin
{
    // Properties
    // =========================================================================

    /**
     * @var Blitz
     */
    public static $plugin;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        self::$plugin = $this;

        $this->_registerComponents();

        $this->_registerVariable();

        $this->_processCacheableRequest();

        // Register events
        $this->_registerElementEvents();
        $this->_registerResaveElementEvents();
        $this->_registerPluginEvents();
        $this->_registerClearCaches();
        $this->_registerGarbageCollection();

        // Register control panel events
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpTemplateRoots();
            $this->_registerCpUrlRules();
            $this->_registerUtilities();
            $this->_registerRedirectAfterInstall();

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

    // Private Methods
    // =========================================================================

    /**
     * Processes cacheable request.
     */
    private function _processCacheableRequest()
    {
        if (RequestHelper::getIsCacheableRequest()) {
            $siteUri = RequestHelper::getRequestedSiteUri();

            if ($siteUri->getIsCacheableUri()) {
                // If output then the script will exit
                $this->outputCache->output($siteUri);

                $this->_registerCacheableRequestEvents($siteUri);
            }
        }
    }

    /**
     * Registers the components
     */
    private function _registerComponents()
    {
        $this->setComponents([
            'cacheTags' => CacheTagsService::class,
            'clearCache' => ClearCacheService::class,
            'flushCache' => FlushCacheService::class,
            'generateCache' => GenerateCacheService::class,
            'outputCache' => OutputCacheService::class,
            'refreshCache' => RefreshCacheService::class,
            'warmCache' => WarmCacheService::class,
            'cacheStorage' => array_merge(
                ['class' => $this->settings->cacheStorageType],
                $this->settings->cacheStorageSettings
            ),
            'cachePurger' => array_merge(
                ['class' => $this->settings->cachePurgerType],
                $this->settings->cachePurgerSettings
            ),
        ]);
    }

    /**
     * Registers variable
     */
    private function _registerVariable()
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('blitz', BlitzVariable::class);
            }
        );
    }

    /**
     * Registers cacheable request events
     *
     * @param SiteUriModel $siteUri
     */
    private function _registerCacheableRequestEvents(SiteUriModel $siteUri)
    {
        // We will need to check if the response is ok again inside the event functions as it may change during the request
        $response = Craft::$app->getResponse();

        // Register element populate event
        Event::on(ElementQuery::class, ElementQuery::EVENT_AFTER_POPULATE_ELEMENT,
            function(PopulateElementEvent $event) use ($response) {
                if ($response->getIsOk()) {
                    $this->generateCache->addElement($event->element);
                }
            }
        );

        // Register element query prepare event
        Event::on(ElementQuery::class, ElementQuery::EVENT_BEFORE_PREPARE,
            function(CancelableEvent $event) use ($response) {
                if ($response->getIsOk()) {
                    /** @var ElementQuery $elementQuery */
                    $elementQuery = $event->sender;
                    $this->generateCache->addElementQuery($elementQuery);
                }
            }
        );

        // Register template page render event
        Event::on(View::class, View::EVENT_AFTER_RENDER_PAGE_TEMPLATE,
            function(TemplateEvent $event) use ($response, $siteUri) {
                if ($response->getIsOk()) {
                    $this->generateCache->save($event->output, $siteUri);
                }
            }
        );
    }

    /**
     * Registers element events
     */
    private function _registerElementEvents()
    {
        // Invalidate elements
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(ElementEvent $event) {
                $this->refreshCache->addElement($event->element);
            }
        );
        Event::on(Elements::class, Elements::EVENT_AFTER_RESAVE_ELEMENT,
            function(ElementEvent $event) {
                $this->refreshCache->addElement($event->element);
            }
        );
        Event::on(Elements::class, Elements::EVENT_AFTER_UPDATE_SLUG_AND_URI,
            function(ElementEvent $event) {
                $this->refreshCache->addElement($event->element);
            }
        );
        Event::on(Structures::class, Structures::EVENT_AFTER_MOVE_ELEMENT,
            function(MoveElementEvent $event) {
                $this->refreshCache->addElement($event->element);
            }
        );

        // Add cache IDs before deleting an element so we can refresh it
        Event::on(Elements::class, Elements::EVENT_BEFORE_DELETE_ELEMENT,
            function(ElementEvent $event) {
                $this->refreshCache->addCacheIds($event->element);
                $this->refreshCache->addElement($event->element);
            }
        );
    }

    /**
     * Registers resave element events
     */
    private function _registerResaveElementEvents()
    {
        // TODO: Add events for propagating elements
        // Turn on batch mode
        Event::on(Elements::class, Elements::EVENT_BEFORE_RESAVE_ELEMENTS,
            function() {
                $this->refreshCache->batchMode = true;
            }
        );
        Event::on(ResaveController::class, ResaveController::EVENT_BEFORE_ACTION,
            function() {
                $this->refreshCache->batchMode = true;
            }
        );

        // Refresh the cache
        Event::on(Elements::class, Elements::EVENT_AFTER_RESAVE_ELEMENTS,
            function() {
                $this->refreshCache->refresh();
            }
        );
        Event::on(ResaveController::class, ResaveController::EVENT_AFTER_ACTION,
            function() {
                $this->refreshCache->refresh();
            }
        );

    }

    /**
     * Registers plugin events
     */
    private function _registerPluginEvents()
    {
        // SEOmatic
        $class = 'nystudio107\seomatic\Seomatic';

        if (class_exists($class) && Craft::$app->getPlugins()->isPluginInstalled('seomatic')) {
            $metaContainers = $class::$plugin->metaContainers ?? null;

            if ($metaContainers && defined($metaContainers::EVENT_INVALIDATE_CONTAINER_CACHES)) {
                Event::on($metaContainers, $metaContainers::EVENT_INVALIDATE_CONTAINER_CACHES,
                    function(nystudio107\seomatic\events\InvalidateContainerCachesEvent $event) {
                        $this->refreshCache->addCacheIds($event->element);
                        $this->refreshCache->refresh();
                    }
                );
            }
        };
    }

    /**
     * Registers clear caches
     */
    private function _registerClearCaches()
    {
        Event::on(ClearCaches::class, ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function(RegisterCacheOptionsEvent $event) {
                $event->options[] = [
                    'key' => 'blitz',
                    'label' => Craft::t('blitz', 'Blitz cache'),
                    'action' => [Blitz::$plugin->clearCache, 'clearAll'],
                ];
            }
        );
    }

    /**
     * Registers garbage collection
     */
    private function _registerGarbageCollection()
    {
        Event::on(Gc::class, Gc::EVENT_RUN,
            function() {
                $this->flushCache->runGarbageCollection();
            }
        );
    }

    /**
     * Registers CP template roots event
     */
    private function _registerCpTemplateRoots()
    {
        Event::on(View::class, View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $purgerDrivers = CachePurgerHelper::getAllDrivers();

                // Use sets and the splat operator rather than array_merge for performance (https://goo.gl/9mntEV)
                $templateRootSets = [$event->roots];

                foreach ($purgerDrivers as $purgerDriver) {
                    $templateRootSets[] = $purgerDriver::getTemplatesRoot();
                }

                $event->roots = array_merge(...$templateRootSets);
            }
        );
    }

    /**
     * Registers CP URL rules event
     */
    private function _registerCpUrlRules()
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules = array_merge([
                        'settings/plugins/blitz' => 'blitz/settings/edit',
                    ],
                    $event->rules
                );
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
                $event->types[] = CacheUtility::class;
            }
        );
    }

    /**
     * Registers redirect after install
     */
    private function _registerRedirectAfterInstall()
    {
        Event::on(Plugins::class, Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    // Redirect to settings page with welcome
                    Craft::$app->getResponse()->redirect(
                        UrlHelper::cpUrl('settings/plugins/blitz', [
                            'welcome' => 1
                        ])
                    )->send();
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
                    'blitz:clear' => [
                        'label' => Craft::t('blitz', 'Clear cache')
                    ],
                    'blitz:flush' => [
                        'label' => Craft::t('blitz', 'Flush cache')
                    ],
                    'blitz:purge' => [
                        'label' => Craft::t('blitz', 'Purge cache')
                    ],
                    'blitz:warm' => [
                        'label' => Craft::t('blitz', 'Warm cache')
                    ],
                    'blitz:refresh-expired' => [
                        'label' => Craft::t('blitz', 'Refresh entire cache')
                    ],
                    'blitz:refresh-tagged' => [
                        'label' => Craft::t('blitz', 'Refresh tagged cache')
                    ],
                ];
            }
        );
    }
}
