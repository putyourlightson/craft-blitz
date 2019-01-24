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
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\TemplateEvent;
use craft\helpers\UrlHelper;
use craft\queue\jobs\ResaveElements;
use craft\queue\Queue;
use craft\services\Elements;
use craft\services\Gc;
use craft\services\Structures;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use putyourlightson\blitz\drivers\BaseDriver;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\purgers\BasePurger;
use putyourlightson\blitz\services\CacheService;
use putyourlightson\blitz\services\ClientService;
use putyourlightson\blitz\services\InvalidateService;
use putyourlightson\blitz\services\RequestService;
use putyourlightson\blitz\utilities\CacheUtility;
use putyourlightson\blitz\variables\BlitzVariable;
use yii\base\Event;
use yii\queue\ExecEvent;

/**
 *
 * @property CacheService $cache
 * @property ClientService $client
 * @property InvalidateService $invalidate
 * @property RequestService $request
 * @property BaseDriver $driver
 * @property BasePurger $purger
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

    /**
     * @var SettingsModel
     */
    public static $settings;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        self::$plugin = $this;

        // Register components
        $this->_registerComponents();

        // Register variable
        $this->_registerVariable();

        // Process cacheable requests
        if ($this->_processCacheableRequest()) {
            // Stop execution
            return;
        }

        // Register events
        $this->_registerElementEvents();
        $this->_registerResaveElementEvents();
        $this->_registerClearCaches();
        $this->_registerGarbageCollection();

        // Register control panel events
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpUrlRules();
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
    protected function afterInstall()
    {
        // Redirect to settings page with welcome
        $url = UrlHelper::cpUrl('settings/plugins/blitz?welcome=1');

        Craft::$app->getResponse()->redirect($url)->send();
    }

    // Private Methods
    // =========================================================================

    /**
     * Processes cacheable requests.
     *
     * @return bool
     */
    private function _processCacheableRequest(): bool
    {
        if ($this->request->getIsCacheableRequest()) {
            $site = Craft::$app->getSites()->getCurrentSite();
            $uri = $this->request->getCurrentUri();

            if ($this->request->getIsCacheableUri($site->id, $uri)) {
                $value = $this->driver->getCachedUri($site->id, $uri);

                // If cached value exists then output it (assuming this has not already been done server-side)
                if ($value) {
                    $this->request->output($value);
                }

                $this->_registerCacheableRequestEvents($site->id, $uri);

                // Stop execution
                return true;
            }
        }

        return false;
    }

    /**
     * Registers the components
     */
    private function _registerComponents()
    {
        $this->setComponents([
            'cache' => CacheService::class,
            'client' => ClientService::class,
            'invalidate' => InvalidateService::class,
            'request' => RequestService::class,
            'driver' => array_merge(['class' => $this->settings->driverType], $this->settings->driverSettings),
            'purger' => array_merge(['class' => $this->settings->purgerType], $this->settings->purgerSettings),
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
        Event::on(ElementQuery::class, ElementQuery::EVENT_BEFORE_PREPARE,
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
                $this->invalidate->clearCacheRecords($siteId, $uri);
            }
        );
        Event::on(View::class, View::EVENT_AFTER_RENDER_PAGE_TEMPLATE,
            function(TemplateEvent $event) use ($response, $siteId, $uri) {
                if ($response->getIsOk()) {
                    $this->cache->saveOutput($event->output, $siteId, $uri);
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
                $this->invalidate->addElement($event->element);
            }
        );
        Event::on(Structures::class, Structures::EVENT_AFTER_MOVE_ELEMENT,
            function(MoveElementEvent $event) {
                $this->invalidate->addElement($event->element);
            }
        );
        Event::on(Elements::class, Elements::EVENT_AFTER_UPDATE_SLUG_AND_URI,
            function(ElementEvent $event) {
                $this->invalidate->addElement($event->element);
            }
        );
        Event::on(Elements::class, Elements::EVENT_BEFORE_DELETE_ELEMENT,
            function(ElementEvent $event) {
                $this->invalidate->addElement($event->element);
            }
        );
    }

    /**
     * Registers resave element events
     */
    private function _registerResaveElementEvents()
    {
        // Turn on batch mode
        Event::on(Queue::class, Queue::EVENT_BEFORE_EXEC,
            function(ExecEvent $event) {
                if ($event->job instanceof ResaveElements) {
                    $this->invalidate->batchMode = true;
                }
            }
        );

        // Refresh the cache
        Event::on(Queue::class, Queue::EVENT_AFTER_EXEC,
            function(ExecEvent $event) {
                if ($event->job instanceof ResaveElements) {
                    $this->invalidate->refreshCache();
                }
            }
        );
        Event::on(Queue::class, Queue::EVENT_AFTER_ERROR,
            function(ExecEvent $event) {
                if ($event->job instanceof ResaveElements) {
                    $this->invalidate->refreshCache();
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
                    'action' => [Blitz::$plugin->invalidate, 'clearCache'],
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
                $this->invalidate->runGarbageCollection();
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