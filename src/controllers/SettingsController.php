<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\base\ComponentInterface;
use craft\errors\MissingComponentException;
use craft\web\Controller;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\storage\BaseCacheStorage;
use putyourlightson\blitz\helpers\CacheStorageHelper;
use putyourlightson\blitz\helpers\CachePurgerHelper;
use putyourlightson\blitz\drivers\purgers\BaseCachePurger;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class SettingsController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Edit the plugin settings.
     *
     * @return Response|null
     */
    public function actionEdit()
    {
        /** @var BaseCacheStorage $storageDriver */
        $storageDriver = CacheStorageHelper::createDriver(
            Blitz::$plugin->settings->cacheStorageType,
            Blitz::$plugin->settings->cacheStorageSettings
        );

        // Validate the driver so that any errors will be displayed
        $storageDriver->validate();

        $storageDrivers = CacheStorageHelper::getAllDrivers();

        /** @var BaseCachePurger $purgerDriver */
        $purgerDriver = CachePurgerHelper::createDriver(
            Blitz::$plugin->settings->cachePurgerType,
            Blitz::$plugin->settings->cachePurgerSettings
        );

        // Validate the purger so that any errors will be displayed
        $purgerDriver->validate();

        $purgerDrivers = CachePurgerHelper::getAllDrivers();

        return $this->renderTemplate('blitz/_settings', [
            'settings' => Blitz::$plugin->settings,
            'config' => Craft::$app->getConfig()->getConfigFromFile('blitz'),
            'storageDriver' => $storageDriver,
            'storageDrivers' => $storageDrivers,
            'storageTypeOptions' => array_map([$this, '_getSelectOption'], $storageDrivers),
            'purgerDriver' => $purgerDriver,
            'purgerDrivers' => $purgerDrivers,
            'purgerTypeOptions' => array_map([$this, '_getSelectOption'], $purgerDrivers),
        ]);
    }

    /**
     * Saves the plugin settings.
     *
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws MissingComponentException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $postedSettings = $request->getBodyParam('settings', []);
        $storageSettings = $request->getBodyParam('cacheStorageSettings', []);
        $purgerSettings = $request->getBodyParam('cachePurgerSettings', []);

        $settings = Blitz::$plugin->settings;
        $settings->setAttributes($postedSettings, false);

        // Apply storage settings excluding type
        $settings->cacheStorageSettings = $storageSettings[$settings->cacheStorageType] ?? [];

        // Create the storage driver so that we can validate it
        /* @var BaseCacheStorage $storageDriver */
        $storageDriver = CacheStorageHelper::createDriver(
            $settings->cacheStorageType,
            $settings->cacheStorageSettings
        );

        // Apply purger settings excluding type
        $settings->cachePurgerSettings = $purgerSettings[$settings->cachePurgerType] ?? [];

        // Create the purger driver so that we can validate it
        /* @var BaseCachePurger $purgerDriver */
        $purgerDriver = CachePurgerHelper::createDriver(
            $settings->cachePurgerType,
            $settings->cachePurgerSettings
        );

        $variables = [
            'settings' => $settings,
            'storageDriver' => $storageDriver,
            'purgerDriver' => $purgerDriver,
        ];

        // Validate
        $settings->validate();
        $storageDriver->validate();
        $purgerDriver->validate();

        if ($settings->hasErrors() || $storageDriver->hasErrors() || $purgerDriver->hasErrors()) {
            Craft::$app->getSession()->setError(Craft::t('blitz', 'Couldn’t save plugin settings.'));

            Craft::$app->getUrlManager()->setRouteParams($variables);

            return null;
        }

        // Save it
        Craft::$app->getPlugins()->savePluginSettings(Blitz::$plugin, $settings->getAttributes());

        if (!$purgerDriver->test()) {
            Craft::$app->getSession()->setError(Craft::t('blitz', 'Plugin settings saved. Purger connection failed.'));
        }
        else {
            Craft::$app->getSession()->setNotice(Craft::t('blitz', 'Plugin settings saved.'));
        }

        return $this->redirectToPostedUrl();
    }

    // Private Methods
    // =========================================================================

    /**
     * Gets select option from a component.
     *
     * @param ComponentInterface $component
     *
     * @return array
     */
    private function _getSelectOption(ComponentInterface $component): array
    {
        return [
            'value' => get_class($component),
            'label' => $component::displayName(),
        ];
    }
}
