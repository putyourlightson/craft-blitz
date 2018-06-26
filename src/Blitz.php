<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz;

use Craft;
use craft\base\Plugin;
use craft\events\ElementEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\TemplateEvent;
use craft\services\Elements;
use craft\services\Structures;
use craft\services\Utilities;
use craft\web\View;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\services\CacheService;
use putyourlightson\blitz\utilities\ClearCacheUtility;
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

        // Register services as components
        $this->setComponents(['cache' => CacheService::class]);

        // If cached version exists then output it (assuming this is not being done server-side)
        if ($this->cache->isCacheableRequest()) {
            $filePath = $this->cache->uriToFilePath(Craft::$app->getRequest()->getUrl());
            if (is_file($filePath)) {
                readfile($filePath);
                exit;
            }
        }

        // Register template render event
        Event::on(View::class, View::EVENT_AFTER_RENDER_TEMPLATE, function(TemplateEvent $event) {
            if ($this->cache->isCacheableRequest()) {
                $this->cache->cacheOutput(Craft::$app->getRequest()->getUrl(), $event->output);
            }
        });

        // Register element events
        $this->_registerElementEvents();

        // Register utilities
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITY_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = ClearCacheUtility::class;
        });
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
        return Craft::$app->getView()->renderTemplate('blitz/settings', [
            'settings' => $this->getSettings(),
            'config' => Craft::$app->getConfig()->getConfigFromFile('blitz'),
        ]);
    }

    // Private Methods
    // =========================================================================

    private function _registerElementEvents()
    {
        // Clear cache
        Event::on(Elements::class, Elements::EVENT_BEFORE_SAVE_ELEMENT, function(ElementEvent $event) {
            $this->cache->clearCacheByElement($event->element);
        });
        Event::on(Structures::class, Structures::EVENT_BEFORE_MOVE_ELEMENT, function(ElementEvent $event) {
            $this->cache->clearCacheByElement($event->element);
        });
        Event::on(Elements::class, Elements::EVENT_AFTER_DELETE_ELEMENT, function(ElementEvent $event) {
            $this->cache->clearCacheByElement($event->element);
        });

        // Cache
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, function(ElementEvent $event) {
            $this->cache->cacheByElement($event->element);
        });
        Event::on(Structures::class, Structures::EVENT_AFTER_MOVE_ELEMENT, function(ElementEvent $event) {
            $this->cache->cacheByElement($event->element);
        });
    }
}