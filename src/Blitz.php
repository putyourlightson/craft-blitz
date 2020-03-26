<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz;

use Craft;
use craft\base\Plugin;
use craft\console\controllers\ResaveController;
use craft\elements\db\ElementQuery;
use craft\events\BatchElementActionEvent;
use craft\events\CancelableEvent;
use craft\events\DeleteElementEvent;
use craft\events\ElementEvent;
use craft\events\PluginEvent;
use craft\events\PopulateElementEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\TemplateEvent;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\Plugins;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\Application;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use putyourlightson\blitz\behaviors\ElementChangedBehavior;
use putyourlightson\blitz\drivers\deployers\BaseDeployer;
use putyourlightson\blitz\drivers\purgers\BaseCachePurger;
use putyourlightson\blitz\drivers\storage\BaseCacheStorage;
use putyourlightson\blitz\drivers\warmers\BaseCacheWarmer;
use putyourlightson\blitz\helpers\IntegrationHelper;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\services\CacheRequestService;
use putyourlightson\blitz\services\CacheTagsService;
use putyourlightson\blitz\services\FlushCacheService;
use putyourlightson\blitz\services\GenerateCacheService;
use putyourlightson\blitz\services\ClearCacheService;
use putyourlightson\blitz\services\RefreshCacheService;
use putyourlightson\blitz\utilities\CacheUtility;
use putyourlightson\blitz\variables\BlitzVariable;
use putyourlightson\logtofile\LogToFile;
use yii\base\Event;

/**
 *
 * @property CacheRequestService $cacheRequest
 * @property CacheTagsService $cacheTags
 * @property ClearCacheService $clearCache
 * @property FlushCacheService $flushCache
 * @property GenerateCacheService $generateCache
 * @property RefreshCacheService $refreshCache
 * @property BaseCacheStorage $cacheStorage
 * @property BaseCacheWarmer $cacheWarmer
 * @property BaseCachePurger $cachePurger
 * @property BaseDeployer $deployer
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

        // Register services and variables before processing the request
        $this->_registerComponents();
        $this->_registerVariables();

        // Register events
        $this->_registerCacheableRequestEvents();
        $this->_registerElementEvents();
        $this->_registerResaveElementEvents();
        $this->_registerIntegrationEvents();
        $this->_registerClearCaches();

        // Register control panel events
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpUrlRules();
            $this->_registerUtilities();
            $this->_registerRedirectAfterInstall();

            if (Craft::$app->getEdition() === Craft::Pro) {
                $this->_registerUserPermissions();
            }
        }
    }

    /**
     * Logs an action
     *
     * @param string $message
     * @param array $params
     * @param string $type
     */
    public function log(string $message, array $params = [], string $type = 'info')
    {
        $message = Craft::t('blitz', $message, $params);

        LogToFile::log($message, 'blitz', $type);
    }

    /**
     * Logs a debug message if debug mode is enabled
     *
     * @param string $message
     * @param array $params
     * @param string $url
     */
    public function debug(string $message, array $params = [], string $url = '')
    {
        if (!$this->settings->debug || empty($message)) {
            return;
        }

        // Get first line of message only so as not to bloat the logs
        $message = strtok($message, "\n");

        $message = Craft::t('blitz', $message, $params);

        if ($url) {
            $message .= ' ['.$url.']';
        }

        LogToFile::log($message, 'blitz', 'debug');
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
     * Registers the components
     */
    private function _registerComponents()
    {
        $this->setComponents([
            'cacheRequest' => CacheRequestService::class,
            'cacheTags' => CacheTagsService::class,
            'clearCache' => ClearCacheService::class,
            'flushCache' => FlushCacheService::class,
            'generateCache' => GenerateCacheService::class,
            'refreshCache' => RefreshCacheService::class,
            'cacheStorage' => array_merge(
                ['class' => $this->settings->cacheStorageType],
                $this->settings->cacheStorageSettings
            ),
            'cacheWarmer' => array_merge(
                ['class' => $this->settings->cacheWarmerType],
                $this->settings->cacheWarmerSettings
            ),
            'cachePurger' => array_merge(
                ['class' => $this->settings->cachePurgerType],
                $this->settings->cachePurgerSettings
            ),
            'deployer' => array_merge(
                ['class' => $this->settings->deployerType],
                $this->settings->deployerSettings
            ),
        ]);
    }

    /**
     * Registers variables
     */
    private function _registerVariables()
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
     */
    private function _registerCacheableRequestEvents()
    {
        // Ensure the request is cacheable
        if (!$this->cacheRequest->getIsCacheableRequest()) {
            return;
        }

        // Ensure the requested site URI is cacheable
        $siteUri = $this->cacheRequest->getRequestedCacheableSiteUri();

        if ($siteUri === null || !$this->cacheRequest->getIsCacheableSiteUri($siteUri)) {
            return;
        }

        // Register application init event
        Event::on(Application::class, Application::EVENT_INIT,
            function() use ($siteUri) {
                if ($response = $this->cacheRequest->getResponse($siteUri)) {
                    // Output the response and end the script
                    Craft::$app->end(0, $response);
                }
            }
        );

        // Register element populate event
        Event::on(ElementQuery::class, ElementQuery::EVENT_AFTER_POPULATE_ELEMENT,
            function(PopulateElementEvent $event) {
                if (Craft::$app->getResponse()->getIsOk() && $event->element !== null) {
                    $this->generateCache->addElement($event->element);
                }
            }
        );

        // Register element query prepare event
        Event::on(ElementQuery::class, ElementQuery::EVENT_BEFORE_PREPARE,
            function(CancelableEvent $event) {
                if (Craft::$app->getResponse()->getIsOk()) {
                    /** @var ElementQuery $elementQuery */
                    $elementQuery = $event->sender;
                    $this->generateCache->addElementQuery($elementQuery);
                }
            }
        );

        // Register after render page template event
        Event::on(View::class, View::EVENT_AFTER_RENDER_PAGE_TEMPLATE,
            function(TemplateEvent $event) use ($siteUri) {
                if (Craft::$app->getResponse()->getIsOk()) {
                    // Save the cached output
                    $this->generateCache->save($event->output, $siteUri);

                    $response = $this->cacheRequest->getResponse($siteUri);

                    if ($response !== null) {
                        // Output the response and end the script
                        Craft::$app->end(0, $response);
                    }
                }
            }
        );
    }

    /**
     * Registers element events
     */
    private function _registerElementEvents()
    {
        // Add cache IDs before hard deleting elements so we can refresh them
        Event::on(Elements::class, Elements::EVENT_BEFORE_DELETE_ELEMENT,
            function(DeleteElementEvent $event) {
                if ($event->hardDelete && $event->element !== null) {
                    $this->refreshCache->addCacheIds($event->element);
                }
            }
        );

        // Set previous status of element so we can compare later
        $events = [
            Elements::EVENT_BEFORE_SAVE_ELEMENT,
            Elements::EVENT_BEFORE_RESAVE_ELEMENT,
            Elements::EVENT_BEFORE_UPDATE_SLUG_AND_URI,
            Elements::EVENT_BEFORE_DELETE_ELEMENT,
            Elements::EVENT_BEFORE_RESTORE_ELEMENT,
        ];

        foreach ($events as $event) {
            Event::on(Elements::class, $event,
                /** @var ElementEvent|BatchElementActionEvent $event */
                function($event) {
                    if ($event->element !== null) {
                        $event->element->attachBehavior(ElementChangedBehavior::BEHAVIOR_NAME, ElementChangedBehavior::class);
                    }
                }
            );
        }

        // Invalidate elements
        $events = [
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            Elements::EVENT_AFTER_RESAVE_ELEMENT,
            Elements::EVENT_AFTER_UPDATE_SLUG_AND_URI,
            Elements::EVENT_AFTER_DELETE_ELEMENT,
            Elements::EVENT_AFTER_RESTORE_ELEMENT,
        ];

        foreach ($events as $event) {
            Event::on(Elements::class, $event,
                /** @var ElementEvent|BatchElementActionEvent $event */
                function($event) {
                    if ($event->element !== null) {
                        $this->refreshCache->addElement($event->element);
                    }
                }
            );
        }
    }

    /**
     * Registers resave elements events
     */
    private function _registerResaveElementEvents()
    {
        // Enable batch mode
        $events = [
            [Elements::class, Elements::EVENT_BEFORE_RESAVE_ELEMENTS],
            [Elements::class, Elements::EVENT_BEFORE_PROPAGATE_ELEMENTS],
            [ResaveController::class, ResaveController::EVENT_BEFORE_ACTION],
        ];

        foreach ($events as $event) {
            Event::on($event[0], $event[1],
                function() {
                    $this->refreshCache->batchMode = true;
                }
            );
        }

        // Refresh the cache
        $events = [
            [Elements::class, Elements::EVENT_AFTER_RESAVE_ELEMENTS],
            [Elements::class, Elements::EVENT_AFTER_PROPAGATE_ELEMENTS],
            [ResaveController::class, ResaveController::EVENT_AFTER_ACTION],
        ];

        foreach ($events as $event) {
            Event::on($event[0], $event[1],
                function() {
                    $this->refreshCache->refresh();
                }
            );
        }
    }

    /**
     * Registers integration events
     */
    private function _registerIntegrationEvents()
    {
        Event::on(Plugins::class, Plugins::EVENT_AFTER_LOAD_PLUGINS,
            function() {
                foreach (IntegrationHelper::getActiveIntegrations() as $integration) {
                    $integration::registerEvents();
                }
            }
        );
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
     * Registers CP URL rules event
     */
    private function _registerCpUrlRules()
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                // Merge so that settings controller action comes first (important!)
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
            function(PluginEvent $event) {
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
                    'blitz:deploy' => [
                        'label' => Craft::t('blitz', 'Remote deploy')
                    ],
                    'blitz:refresh' => [
                        'label' => Craft::t('blitz', 'Refresh cache')
                    ],
                    'blitz:refresh-expired' => [
                        'label' => Craft::t('blitz', 'Refresh expired cache')
                    ],
                    'blitz:refresh-urls' => [
                        'label' => Craft::t('blitz', 'Refresh cached URLs')
                    ],
                    'blitz:refresh-tagged' => [
                        'label' => Craft::t('blitz', 'Refresh tagged cache')
                    ],
                ];
            }
        );
    }
}
