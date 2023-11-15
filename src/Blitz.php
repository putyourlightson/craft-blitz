<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\console\controllers\ResaveController;
use craft\events\BatchElementActionEvent;
use craft\events\DeleteElementEvent;
use craft\events\ElementEvent;
use craft\events\MoveElementEvent;
use craft\events\PluginEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\log\MonologTarget;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\Plugins;
use craft\services\Structures;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\Application;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use putyourlightson\blitz\behaviors\ElementChangedBehavior;
use putyourlightson\blitz\drivers\deployers\BaseDeployer;
use putyourlightson\blitz\drivers\generators\BaseCacheGenerator;
use putyourlightson\blitz\drivers\purgers\BaseCachePurger;
use putyourlightson\blitz\drivers\storage\BaseCacheStorage;
use putyourlightson\blitz\helpers\IntegrationHelper;
use putyourlightson\blitz\helpers\RefreshCacheHelper;
use putyourlightson\blitz\models\RefreshDataModel;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\services\CacheRequestService;
use putyourlightson\blitz\services\CacheTagsService;
use putyourlightson\blitz\services\ClearCacheService;
use putyourlightson\blitz\services\ExpireCacheService;
use putyourlightson\blitz\services\FlushCacheService;
use putyourlightson\blitz\services\GenerateCacheService;
use putyourlightson\blitz\services\RefreshCacheService;
use putyourlightson\blitz\utilities\CacheUtility;
use putyourlightson\blitz\variables\BlitzVariable;
use putyourlightson\blitz\widgets\CacheWidget;
use putyourlightson\blitzhints\BlitzHints;
use yii\base\Controller;
use yii\base\Event;
use yii\di\Instance;
use yii\log\Logger;
use yii\queue\Queue;
use yii\web\Response;

/**
 * @property-read CacheRequestService $cacheRequest
 * @property-read CacheTagsService $cacheTags
 * @property-read ClearCacheService $clearCache
 * @property-read ExpireCacheService $expireCache
 * @property-read FlushCacheService $flushCache
 * @property-read GenerateCacheService $generateCache
 * @property-read RefreshCacheService $refreshCache
 * @property-read BaseCacheStorage $cacheStorage
 * @property-read BaseCacheGenerator $cacheGenerator
 * @property-read BaseCachePurger $cachePurger
 * @property-read BaseDeployer $deployer
 * @property-read SettingsModel $settings
 */
class Blitz extends Plugin
{
    /**
     * @var Blitz
     */
    public static Blitz $plugin;

    /**
     * @inerhitdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'cacheRequest' => ['class' => CacheRequestService::class],
                'cacheTags' => ['class' => CacheTagsService::class],
                'clearCache' => ['class' => ClearCacheService::class],
                'expireCache' => ['class' => ExpireCacheService::class],
                'flushCache' => ['class' => FlushCacheService::class],
                'generateCache' => ['class' => GenerateCacheService::class],
                'refreshCache' => ['class' => RefreshCacheService::class],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public string $schemaVersion = '4.5.0';

    /**
     * @inheritdoc
     */
    public string $minVersionRequired = '3.10.0';

    /**
     * The queue to use for running jobs.
     *
     * @see \craft\feedme\Plugin::$queue
     * @since 4.9.0
     */
    public Queue|array|string $queue = 'queue';

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $this->_registerComponents();
        $this->_registerInstances();
        $this->_registerVariables();
        $this->_registerLogTarget();

        // Register events
        $this->_registerCacheableRequestEvents();
        $this->_registerElementEvents();
        $this->_registerResaveElementEvents();
        $this->_registerStructureEvents();
        $this->_registerIntegrationEvents();
        $this->_registerClearCaches();

        // Register control panel events
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpUrlRules();
            $this->_registerUtilities();
            $this->_registerWidgets();
            $this->_registerRedirectAfterInstall();

            if (Craft::$app->getEdition() === Craft::Pro) {
                $this->_registerUserPermissions();
            }
        }

        // Register hints after utilities
        $this->_registerHints();
    }

    /**
     * Logs a message
     */
    public function log(string $message, array $params = [], int $type = Logger::LEVEL_INFO): void
    {
        $message = Craft::t('blitz', $message, $params);

        Craft::getLogger()->log($message, $type, 'blitz');
    }

    /**
     * Logs a debug message if debug mode is enabled
     */
    public function debug(string $message, array $params = [], string $url = ''): void
    {
        if (!$this->settings->debug || empty($message)) {
            return;
        }

        // Get first line of message only so as not to bloat the logs
        $message = strtok($message, "\n");

        $message = Craft::t('blitz', $message, $params);

        if ($url) {
            $message .= ' [' . $url . ']';
        }

        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'blitz');
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): SettingsModel
    {
        return new SettingsModel();
    }

    /**
     * Registers the components that should be defined via settings, providing
     * they have not already been set in `$pluginConfigs`.
     *
     * @see Plugins::$pluginConfigs
     */
    private function _registerComponents(): void
    {
        if (!$this->has('cacheStorage')) {
            $this->set('cacheStorage', array_merge(
                ['class' => $this->settings->cacheStorageType],
                $this->settings->cacheStorageSettings,
            ));
        }

        if (!$this->has('cacheGenerator')) {
            $this->set('cacheGenerator', array_merge(
                ['class' => $this->settings->cacheGeneratorType],
                $this->settings->cacheGeneratorSettings,
            ));
        }

        if (!$this->has('cachePurger')) {
            $this->set('cachePurger', array_merge(
                ['class' => $this->settings->cachePurgerType],
                $this->settings->cachePurgerSettings,
            ));
        }

        if (!$this->has('deployer')) {
            $this->set('deployer', array_merge(
                ['class' => $this->settings->deployerType],
                $this->settings->deployerSettings,
            ));
        }
    }

    /**
     * Registers instances configured via `config/app.php`, ensuring they are of the correct type.
     */
    private function _registerInstances(): void
    {
        $this->queue = Instance::ensure($this->queue, Queue::class);
    }

    /**
     * Registers variables
     */
    private function _registerVariables(): void
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
     * Registers a custom log target, keeping the format as simple as possible.
     *
     * @see LineFormatter::SIMPLE_FORMAT
     */
    private function _registerLogTarget(): void
    {
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => 'blitz',
            'categories' => ['blitz'],
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => false,
            'formatter' => new LineFormatter(
                format: "[%datetime%] %message%\n",
                dateFormat: 'Y-m-d H:i:s',
            ),
        ]);
    }

    /**
     * Registers the Blitz hints module.
     */
    private function _registerHints(): void
    {
        if (!$this->settings->hintsEnabled) {
            return;
        }

        BlitzHints::bootstrap();
    }

    /**
     * Registers cacheable request events
     */
    private function _registerCacheableRequestEvents(): void
    {
        // Register application init event
        Event::on(Application::class, Application::EVENT_INIT,
            function() {
                $this->cacheRequest->setDefaultCacheControlHeader();

                if (!$this->cacheRequest->getIsCacheableRequest()) {
                    return;
                }

                $siteUri = $this->cacheRequest->getRequestedCacheableSiteUri();

                if ($siteUri === null || !$this->cacheRequest->getIsCacheableSiteUri($siteUri)) {
                    return;
                }

                $cachedResponse = $this->cacheRequest->getCachedResponse($siteUri);
                if ($cachedResponse) {
                    // Send the cached response and exit early, without allowing the full application life cycle to complete.
                    $cachedResponse->send();
                    exit();
                }

                if ($this->settings->cachingEnabled === false) {
                    return;
                }

                $this->generateCache->registerElementPrepareEvents();

                Event::on(Response::class, Response::EVENT_AFTER_PREPARE,
                    function(Event $event) use ($siteUri) {
                        /** @var Response|null $response */
                        $response = $event->sender;
                        $this->cacheRequest->saveAndPrepareResponse($response, $siteUri);
                    }
                );
            }
        );
    }

    /**
     * Registers element events
     */
    private function _registerElementEvents(): void
    {
        // Add cache IDs before hard deleting elements, so we can refresh them
        Event::on(Elements::class, Elements::EVENT_BEFORE_DELETE_ELEMENT,
            function(DeleteElementEvent $event) {
                if ($event->hardDelete) {
                    $element = $event->element;
                    $cacheIds = RefreshCacheHelper::getElementCacheIds(
                        $element::class,
                        RefreshDataModel::createFromElement($element),
                    );
                    $this->refreshCache->addCacheIds($cacheIds);
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
                function(ElementEvent|BatchElementActionEvent $event) {
                    /** @var Element $element */
                    $element = $event->element;
                    $element->attachBehavior(ElementChangedBehavior::BEHAVIOR_NAME, ElementChangedBehavior::class);
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
                function(ElementEvent|BatchElementActionEvent $event) {
                    $this->refreshCache->addElement($event->element);
                }
            );
        }
    }

    /**
     * Registers resave element events
     */
    private function _registerResaveElementEvents(): void
    {
        // Enable batch mode
        $events = [
            [Elements::class, Elements::EVENT_BEFORE_RESAVE_ELEMENTS],
            [Elements::class, Elements::EVENT_BEFORE_PROPAGATE_ELEMENTS],
            [ResaveController::class, Controller::EVENT_BEFORE_ACTION],
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
            [ResaveController::class, Controller::EVENT_AFTER_ACTION],
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
     * Registers structure events
     */
    private function _registerStructureEvents(): void
    {
        if ($this->settings->refreshCacheWhenElementMovedInStructure) {
            Event::on(Structures::class, Structures::EVENT_AFTER_MOVE_ELEMENT,
                function(MoveElementEvent $event) {
                    $this->refreshCache->addElement($event->element);
                }
            );
        }
    }

    /**
     * Registers integration events
     */
    private function _registerIntegrationEvents(): void
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
    private function _registerClearCaches(): void
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
    private function _registerCpUrlRules(): void
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
    private function _registerUtilities(): void
    {
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITY_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = CacheUtility::class;
            }
        );
    }

    /**
     * Registers widgets
     */
    private function _registerWidgets(): void
    {
        Event::on(Dashboard::class, Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function(RegisterComponentTypesEvent $event) {
                if (!empty(CacheWidget::getActions())) {
                    $event->types[] = CacheWidget::class;
                }
            }
        );
    }

    /**
     * Registers redirect after install
     */
    private function _registerRedirectAfterInstall(): void
    {
        Event::on(Plugins::class, Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function(PluginEvent $event) {
                if ($event->plugin === $this) {
                    // Redirect to settings page with welcome
                    Craft::$app->getResponse()->redirect(
                        UrlHelper::cpUrl('settings/plugins/blitz', [
                            'welcome' => 1,
                        ])
                    )->send();
                }
            }
        );
    }

    /**
     * Registers user permissions
     */
    private function _registerUserPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => 'Blitz',
                    'permissions' => [
                        'blitz:clear' => [
                            'label' => Craft::t('blitz', 'Clear cache'),
                        ],
                        'blitz:flush' => [
                            'label' => Craft::t('blitz', 'Flush cache'),
                        ],
                        'blitz:purge' => [
                            'label' => Craft::t('blitz', 'Purge cache'),
                        ],
                        'blitz:generate' => [
                            'label' => Craft::t('blitz', 'Generate cache'),
                        ],
                        'blitz:deploy' => [
                            'label' => Craft::t('blitz', 'Remote deploy'),
                        ],
                        'blitz:refresh' => [
                            'label' => Craft::t('blitz', 'Refresh cache'),
                        ],
                        'blitz:refresh-expired' => [
                            'label' => Craft::t('blitz', 'Refresh expired cache'),
                        ],
                        'blitz:refresh-site' => [
                            'label' => Craft::t('blitz', 'Refresh site cache'),
                        ],
                        'blitz:refresh-urls' => [
                            'label' => Craft::t('blitz', 'Refresh cached URLs'),
                        ],
                        'blitz:refresh-tagged' => [
                            'label' => Craft::t('blitz', 'Refresh tagged cache'),
                        ],
                    ],
                ];
            }
        );
    }
}
