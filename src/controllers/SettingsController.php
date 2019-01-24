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
use putyourlightson\blitz\drivers\BaseDriver;
use putyourlightson\blitz\helpers\DriverHelper;
use putyourlightson\blitz\helpers\PurgerHelper;
use putyourlightson\blitz\purgers\BasePurger;
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
        /** @var BaseDriver $driver */
        $driver = DriverHelper::createDriver(
            Blitz::$settings->driverType,
            Blitz::$settings->driverSettings
        );

        // Validate the driver so that any errors will be displayed
        $driver->validate();

        $drivers = DriverHelper::getAllDrivers();

        /** @var BasePurger $purger */
        $purger = PurgerHelper::createPurger(
            Blitz::$settings->purgerType,
            Blitz::$settings->purgerSettings
        );

        // Validate the purger so that any errors will be displayed
        $purger->validate();

        $purgers = PurgerHelper::getAllPurgers();

        return $this->renderTemplate('blitz/_settings', [
            'settings' => Blitz::$settings,
            'parsedApiKey' => Craft::parseEnv(Blitz::$settings->apiKey),
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

        Blitz::$settings->setAttributes($postedSettings, false);

        // Remove driver type from settings
        Blitz::$settings->driverSettings = $driverSettings[Blitz::$settings->driverType] ?? [];

        // Create the driver so that we can validate it
        /* @var BaseDriver $driver */
        $driver = DriverHelper::createDriver(
            Blitz::$settings->driverType,
            Blitz::$settings->driverSettings
        );

        // Remove purger type from settings
        Blitz::$settings->purgerSettings = $purgerSettings[Blitz::$settings->purgerType] ?? [];

        // Create the purger so that we can validate it
        /* @var BasePurger $purger */
        $purger = PurgerHelper::createPurger(
            Blitz::$settings->purgerType,
            Blitz::$settings->purgerSettings
        );

        $variables = [
            'settings' => Blitz::$settings,
            'driver' => $driver,
            'purger' => $purger,
        ];

        // Validate
        Blitz::$settings->validate();
        $driver->validate();
        $purger->validate();

        if (Blitz::$settings->hasErrors() || $driver->hasErrors() || $purger->hasErrors()) {
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
        Craft::$app->getPlugins()->savePluginSettings(Blitz::$plugin, Blitz::$settings->getAttributes());

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
