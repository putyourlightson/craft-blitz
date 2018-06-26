<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\TemplateEvent;
use craft\services\Utilities;
use craft\web\Request;
use craft\web\View;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\services\CacheService;
use putyourlightson\blitz\utilities\ClearCacheUtility;
use yii\base\Event;

/**
 *
 * @property CacheService $cache
 * @property mixed $cpNavItem
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

        // Register events
        Event::on(View::class, View::EVENT_AFTER_RENDER_TEMPLATE, function(TemplateEvent $event) {
            if ($this->cache->isCacheableRequest()) {
                $this->cache->cacheOutput(Craft::$app->getRequest()->getUrl(), $event->output);
            }
        });

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
            'settings' => $this->getSettings()
        ]);
    }
}