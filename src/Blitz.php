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
use craft\events\RegisterUserPermissionsEvent;
use craft\events\TemplateEvent;
use craft\models\Site;
use craft\services\Elements;
use craft\services\Structures;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\Response;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use putyourlightson\blitz\drivers\BaseDriver;
use putyourlightson\blitz\helpers\DriverHelper;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\services\CacheService;
use putyourlightson\blitz\utilities\CacheUtility;
use putyourlightson\blitz\variables\BlitzVariable;
use yii\base\Event;
use yii\base\InvalidConfigException;

/**
 *
 * @property CacheService $cache
 * @property BaseDriver $driver
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

        // Register services
        $this->setComponents([
            'cache' => CacheService::class,
        ]);

        // Set driver
        $this->setDriver();

        // Register variable
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            /** @var CraftVariable $variable */
            $variable = $event->sender;
            $variable->set('blitz', BlitzVariable::class);
        });

        $request = Craft::$app->getRequest();

        // Process request
        if ($this->cache->getIsCacheableRequest()) {
            $site = Craft::$app->getSites()->getCurrentSite();

            // Get URI from absolute URL
            $uri = $this->_getUri($site, $request->getAbsoluteUrl());

            if ($this->cache->getIsCacheableUri($site->id, $uri)) {
                // If cached value exists then output it (assuming this has not already been done server-side)
                $value = $this->driver->getCachedUri($site->id, $uri);

                if ($value) {
                    $this->_output($value);
                }

                $this->_registerCacheableRequestEvents($site->id, $uri);
            }
        }
        else if ($request->getIsCpRequest()) {
            $this->_registerElementEvents();

            $this->_registerUtilities();

            if (Craft::$app->getEdition() === Craft::Pro) {
                $this->_registerUserPermissions();
            }
        }

        if ($request->getIsCpRequest() || $request->getIsConsoleRequest()) {
            $this->_registerClearCaches();
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeSaveSettings(): bool
    {
        $settings = $this->getSettings();

        // Remove driver type from settings
        $settings->driverSettings = $settings->driverSettings[$settings->driverType] ?? [];

        // Create the driver so that we can validate it
        /* @var BaseDriver $driver */
        $driver = DriverHelper::createDriver(
            $settings->driverType,
            $settings->driverSettings
        );

        if (!$driver->validate()) {
            return false;
        }

        return parent::beforeSaveSettings();
    }

    // Protected Methods
    // =========================================================================

    /**
     * Sets the driver
     */
    protected function setDriver()
    {
        $settings = $this->getSettings();

        try {
            $this->set('driver', array_merge(
                ['class' => $settings->driverType],
                $settings->driverSettings[$settings->driverType] ?? []
            ));
        }
        catch (InvalidConfigException $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }
    }

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
        $settings = $this->getSettings();

        $allDrivers = [];
        $driverTypeOptions = [];

        /** @var BaseDriver[] $allDriverTypes */
        $allDriverTypes = DriverHelper::getAllDriverTypes();

        $settings->driverType = $settings->driverType ?: $allDriverTypes[0];

        /** @var BaseDriver $driver */
        $driver = DriverHelper::createDriver(
            $settings->driverType,
            $settings->driverSettings
        );

        // Validate the driver so that any errors will be displayed
        $driver->validate();

        foreach ($allDriverTypes as $class) {
            if ($class::isSelectable()) {
                $allDrivers[] = DriverHelper::createDriver($class);
                $driverTypeOptions[] = [
                    'value' => $class,
                    'label' => $class::displayName(),
                ];
            }
        }

        return Craft::$app->getView()->renderTemplate('blitz/_settings', [
            'settings' => $settings,
            'config' => Craft::$app->getConfig()->getConfigFromFile('blitz'),
            'driver' => $driver,
            'allDrivers' => $allDrivers,
            'driverTypeOptions' => $driverTypeOptions,
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
     * Outputs a given value.
     *
     * @param string $value
     *
     * @return string
     */
    private function _output(string $value)
    {
        header_remove('X-Powered-By');

        if ($this->getSettings()->sendPoweredByHeader) {
            $header = Craft::$app->getConfig()->getGeneral()->sendPoweredByHeader ? 'Craft CMS, ' : '';
            header('X-Powered-By: '.$header.'Blitz');
        }

        exit($value.'<!-- Served by Blitz -->');
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
        Event::on(Elements::class, Elements::EVENT_AFTER_UPDATE_SLUG_AND_URI,
            function(ElementEvent $event) {
                $this->cache->invalidateElement($event->element);
            }
        );
        Event::on(Elements::class, Elements::EVENT_BEFORE_DELETE_ELEMENT,
            function(ElementEvent $event) {
                $this->cache->invalidateElement($event->element);
            }
        );

        // Invalidate cache after response is prepared
        Craft::$app->getResponse()->on(Response::EVENT_AFTER_PREPARE,
            function() {
                $this->cache->invalidateCache();
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
     * Registers clear caches
     */
    private function _registerClearCaches()
    {
        Event::on(ClearCaches::class, ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function(RegisterCacheOptionsEvent $event) {
                $event->options[] = [
                    'key' => 'blitz',
                    'label' => Craft::t('blitz', 'Blitz cache'),
                    'action' => [Blitz::$plugin->cache, 'clearCache'],
                ];
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