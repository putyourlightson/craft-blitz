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
use putyourlightson\blitz\helpers\DriverHelper;
use putyourlightson\blitz\helpers\PurgerHelper;
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
        /** @var BaseCacheStorage $driver */
        $driver = DriverHelper::createDriver(
            Blitz::$plugin->settings->driverType,
            Blitz::$plugin->settings->driverSettings
        );

        // Validate the driver so that any errors will be displayed
        $driver->validate();

        $drivers = DriverHelper::getAllDrivers();

        /** @var BaseCachePurger $purger */
        $purger = PurgerHelper::createPurger(
            Blitz::$plugin->settings->purgerType,
            Blitz::$plugin->settings->purgerSettings
        );

        // Validate the purger so that any errors will be displayed
        $purger->validate();

        $purgers = PurgerHelper::getAllPurgers();

        return $this->renderTemplate('blitz/_settings', [
            'settings' => Blitz::$plugin->settings,
            'parsedApiKey' => Craft::parseEnv(Blitz::$plugin->settings->apiKey),
            'config' => Craft::$app->getConfig()->getConfigFromFile('blitz'),
            'driver' => $driver,
            'drivers' => $drivers,
            'driverTypeOptions' => array_map([$this, '_getSelectOption'], $drivers),
            'purger' => $purger,
            'purgers' => $purgers,
            'purgerTypeOptions' => array_map([$this, '_getSelectOption'], $purgers),
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
        $driverSettings = $request->getBodyParam('driverSettings', []);
        $purgerSettings = $request->getBodyParam('purgerSettings', []);

        $settings = Blitz::$plugin->settings;
        $settings->setAttributes($postedSettings, false);

        // Apply driver settings excluding type
        $settings->driverSettings = $driverSettings[$settings->driverType] ?? [];

        // Create the driver so that we can validate it
        /* @var BaseCacheStorage $driver */
        $driver = DriverHelper::createDriver(
            $settings->driverType,
            $settings->driverSettings
        );

        // Apply purger settings excluding type
        $settings->purgerSettings = $purgerSettings[$settings->purgerType] ?? [];

        // Create the purger so that we can validate it
        /* @var BaseCachePurger $purger */
        $purger = PurgerHelper::createPurger(
            $settings->purgerType,
            $settings->purgerSettings
        );

        $variables = [
            'settings' => $settings,
            'driver' => $driver,
            'purger' => $purger,
        ];

        // Validate
        $settings->validate();
        $driver->validate();
        $purger->validate();

        if ($settings->hasErrors() || $driver->hasErrors() || $purger->hasErrors()) {
            Craft::$app->getSession()->setError(Craft::t('blitz', 'Couldn’t save plugin settings.'));

            Craft::$app->getUrlManager()->setRouteParams($variables);

            return null;
        }

        if (!$purger->test()) {
            Craft::$app->getSession()->setError(Craft::t('blitz', 'Purger connection failed.'));

            Craft::$app->getUrlManager()->setRouteParams($variables);

            return null;
        }

        // Save it
        Craft::$app->getPlugins()->savePluginSettings(Blitz::$plugin, $settings->getAttributes());

        Craft::$app->getSession()->setNotice(Craft::t('blitz', 'Plugin settings saved.'));

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
