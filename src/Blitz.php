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
use craft\services\Elements;
use craft\services\Structures;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\web\View;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\services\CacheService;
use putyourlightson\blitz\utilities\CacheUtility;
use yii\base\Event;

/**
 *
 * @property CacheService $cache
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

        $request = Craft::$app->getRequest();

        // Register services as components
        $this->setComponents(['cache' => CacheService::class]);

        // Cacheable request
        if ($this->cache->getIsCacheableRequest()) {
            $uri = $request->getUrl();
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;

            if ($this->cache->getIsCacheableUri($uri)) {
                // If cached version exists then output it (assuming this has not already been done server-side)
                $filePath = $this->cache->uriToFilePath($siteId, $uri);
                if (is_file($filePath)) {
                    echo file_get_contents($filePath).'<!-- Served by Blitz -->';
                    exit;
                }

                $this->_registerCacheableRequestEvents($siteId, $uri);
            }
        }

        // CP request
        else if ($request->getIsCpRequest()) {
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

    private function _registerElementEvents()
    {
        // Cache elements
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(ElementEvent $event) {
                $this->cache->cacheByElement($event->element);
            }
        );
        Event::on(Structures::class, Structures::EVENT_AFTER_MOVE_ELEMENT,
            function(MoveElementEvent $event) {
                $this->cache->cacheByElement($event->element);
            }
        );
        Event::on(Elements::class, Elements::EVENT_BEFORE_DELETE_ELEMENT,
            function(ElementEvent $event) {
                $this->cache->cacheByElement($event->element);
            }
        );
    }

    /**
     * @param int $siteId
     * @param string $uri
     */
    private function _registerCacheableRequestEvents(int $siteId, string $uri)
    {
        // We'll need to check if the response is ok again inside the event functions as it may change throughout the request
        $response = Craft::$app->getResponse();

        // Register element populate event
        Event::on(ElementQuery::class, ElementQuery::EVENT_AFTER_POPULATE_ELEMENT,
            function(PopulateElementEvent $event) use ($response, $siteId, $uri) {
                if ($response->getIsOk()) {
                    $this->cache->addElementCache($event->element, $siteId, $uri);
                }
            }
        );

        // Register element query prepare event
        Event::on(ElementQuery::class, ElementQuery::EVENT_AFTER_PREPARE,
            function(CancelableEvent $event) use ($response, $siteId, $uri) {
                if ($response->getIsOk()) {
                    /** @var ElementQuery $elementQuery */
                    $elementQuery = $event->sender;
                    $this->cache->addElementQueryCache($elementQuery, $siteId, $uri);
                }
            }
        );

        // Register template page render events
        Event::on(View::class, View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            function() use ($siteId, $uri) {
                $this->cache->clearCacheRecord($siteId, $uri);
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