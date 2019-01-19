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
        $settings = Blitz::$plugin->getSettings();

        /** @var BaseDriver $driver */
        $driver = DriverHelper::createDriver(
            $settings->driverType,
            $settings->driverSettings
        );

        // Validate the driver so that any errors will be displayed
        $driver->validate();

        $drivers = DriverHelper::getAllDrivers();

        /** @var BasePurger $purger */
        $purger = PurgerHelper::createPurger(
            $settings->purgerType,
            $settings->purgerSettings
        );

        // Validate the purger so that any errors will be displayed
        $purger->validate();

        $purgers = PurgerHelper::getAllPurgers();

        return $this->renderTemplate('blitz/_settings', [
            'settings' => $settings,
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

        $settings = Blitz::$plugin->getSettings();
        $settings->setAttributes($postedSettings, false);

        // Remove driver type from settings
        $settings->driverSettings = $driverSettings[$settings->driverType] ?? [];

        // Create the driver so that we can validate it
        /* @var BaseDriver $driver */
        $driver = DriverHelper::createDriver(
            $settings->driverType,
            $settings->driverSettings
        );

        // Remove purger type from settings
        $settings->purgerSettings = $purgerSettings[$settings->purgerType] ?? [];

        // Create the purger so that we can validate it
        /* @var BasePurger $purger */
        $purger = PurgerHelper::createPurger(
            $settings->purgerType,
            $settings->purgerSettings
        );

        // Validate
        $settings->validate();
        $driver->validate();
        $purger->validate();

        if ($settings->hasErrors() || $driver->hasErrors() || $purger->hasErrors()) {
            Craft::$app->getSession()->setError(Craft::t('blitz', 'Couldn’t save plugin settings.'));

            // Send the settings back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'settings' => $settings,
                'driver' => $driver,
                'purger' => $purger,
            ]);

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
