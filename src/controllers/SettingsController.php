<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\errors\MissingComponentException;
use craft\web\Controller;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\DriverHelper;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class SettingsController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Saves the plugin settings.
     *
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws MissingComponentException
     * @throws InvalidConfigException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $postedSettings = Craft::$app->getRequest()->getBodyParam('settings', []);
        $driverSettings = Craft::$app->getRequest()->getBodyParam('driverSettings', []);

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

        // Validate settings and transport adapter
        $settings->validate();
        $driver->validate();

        if ($settings->hasErrors() OR $driver->hasErrors()) {
            Craft::$app->getSession()->setError(Craft::t('blitz', 'Couldn’t save plugin settings.'));

            // Send the settings back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'settings' => $settings,
                'driver' => $driver
            ]);

            return null;
        }

        // Save it
        Craft::$app->getPlugins()->savePluginSettings(Blitz::$plugin, $settings->getAttributes());

        Craft::$app->getSession()->setNotice(Craft::t('blitz', 'Plugin settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
