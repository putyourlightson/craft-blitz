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
use putyourlightson\blitz\drivers\deployers\BaseDeployer;
use putyourlightson\blitz\drivers\storage\BaseCacheStorage;
use putyourlightson\blitz\drivers\warmers\BaseCacheWarmer;
use putyourlightson\blitz\helpers\BaseDriverHelper;
use putyourlightson\blitz\helpers\CacheStorageHelper;
use putyourlightson\blitz\helpers\CachePurgerHelper;
use putyourlightson\blitz\drivers\purgers\BaseCachePurger;
use putyourlightson\blitz\helpers\CacheWarmerHelper;
use putyourlightson\blitz\helpers\DeployerHelper;
use putyourlightson\blitz\models\SettingsModel;
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
        $settings = Blitz::$plugin->settings;

        // Get site options
        $siteOptions = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteOptions[$site->id] = $site->name;
        }

        /** @var BaseCacheStorage $storageDriver */
        $storageDriver = BaseDriverHelper::createDriver(
            $settings->cacheStorageType,
            $settings->cacheStorageSettings
        );

        // Validate the driver so that any errors will be displayed
        $storageDriver->validate();

        $storageDrivers = CacheStorageHelper::getAllDrivers();

        /** @var BaseCacheWarmer $warmerDriver */
        $warmerDriver = BaseDriverHelper::createDriver(
            $settings->cacheWarmerType,
            $settings->cacheWarmerSettings
        );

        // Validate the warmer so that any errors will be displayed
        $warmerDriver->validate();

        $warmerDrivers = CacheWarmerHelper::getAllDrivers();

        /** @var BaseCachePurger $purgerDriver */
        $purgerDriver = BaseDriverHelper::createDriver(
            $settings->cachePurgerType,
            $settings->cachePurgerSettings
        );

        // Validate and test the purger so that any errors will be displayed
        $purgerDriver->validate();
        $purgerDriver->test();

        $purgerDrivers = CachePurgerHelper::getAllDrivers();

        /** @var BaseDeployer $deployerDriver */
        $deployerDriver = BaseDriverHelper::createDriver(
            $settings->deployerType,
            $settings->deployerSettings
        );

        // Validate and test the deployer so that any errors will be displayed
        $deployerDriver->validate();
        $deployerDriver->test();

        $deployerDrivers = DeployerHelper::getAllDrivers();

        return $this->renderTemplate('blitz/_settings', [
            'settings' => $settings,
            'config' => Craft::$app->getConfig()->getConfigFromFile('blitz'),
            'siteOptions' => $siteOptions,
            'storageDriver' => $storageDriver,
            'storageDrivers' => $storageDrivers,
            'storageTypeOptions' => array_map([$this, '_getSelectOption'], $storageDrivers),
            'warmerDriver' => $warmerDriver,
            'warmerDrivers' => $warmerDrivers,
            'warmerTypeOptions' => array_map([$this, '_getSelectOption'], $warmerDrivers),
            'purgerDriver' => $purgerDriver,
            'purgerDrivers' => $purgerDrivers,
            'purgerTypeOptions' => array_map([$this, '_getSelectOption'], $purgerDrivers),
            'deployerDriver' => $deployerDriver,
            'deployerDrivers' => $deployerDrivers,
            'deployerTypeOptions' => array_map([$this, '_getSelectOption'], $deployerDrivers),
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
        $warmerSettings = $request->getBodyParam('cacheWarmerSettings', []);
        $purgerSettings = $request->getBodyParam('cachePurgerSettings', []);
        $deployerSettings = $request->getBodyParam('deployerSettings', []);

        $settings = Blitz::$plugin->settings;
        $settings->setAttributes($postedSettings, false);

        // Apply storage settings excluding type
        $settings->cacheStorageSettings = $storageSettings[$settings->cacheStorageType] ?? [];

        // Create the storage driver so that we can validate it
        /* @var BaseCacheStorage $storageDriver */
        $storageDriver = BaseDriverHelper::createDriver(
            $settings->cacheStorageType,
            $settings->cacheStorageSettings
        );

        // Apply warmer settings excluding type
        $settings->cacheWarmerSettings = $warmerSettings[$settings->cacheWarmerType] ?? [];

        // Create the warmer driver so that we can validate it
        /* @var BaseCacheWarmer $storageDriver */
        $warmerDriver = BaseDriverHelper::createDriver(
            $settings->cacheWarmerType,
            $settings->cacheWarmerSettings
        );

        // Apply purger settings excluding type
        $settings->cachePurgerSettings = $purgerSettings[$settings->cachePurgerType] ?? [];

        // Create the purger driver so that we can validate it
        /* @var BaseCachePurger $purgerDriver */
        $purgerDriver = BaseDriverHelper::createDriver(
            $settings->cachePurgerType,
            $settings->cachePurgerSettings
        );

        // Apply deployer settings excluding type
        $settings->deployerSettings = $deployerSettings[$settings->deployerType] ?? [];

        // Create the deployer driver so that we can validate it
        /* @var BaseDeployer $deployerDriver */
        $deployerDriver = BaseDriverHelper::createDriver(
            $settings->deployerType,
            $settings->deployerSettings
        );

        $variables = [
            'settings' => $settings,
            'storageDriver' => $storageDriver,
            'warmerDriver' => $warmerDriver,
            'purgerDriver' => $purgerDriver,
            'deployerDriver' => $deployerDriver,
        ];

        // Validate
        $settings->validate();
        $storageDriver->validate();
        $warmerDriver->validate();
        $purgerDriver->validate();
        $deployerDriver->validate();

        if ($settings->hasErrors() || $storageDriver->hasErrors() || $warmerDriver->hasErrors() || $purgerDriver->hasErrors() || $deployerDriver->hasErrors()) {
            Craft::$app->getSession()->setError(Craft::t('blitz', 'Couldn’t save plugin settings.'));

            Craft::$app->getUrlManager()->setRouteParams($variables);

            return null;
        }

        // Save it
        Craft::$app->getPlugins()->savePluginSettings(Blitz::$plugin, $settings->getAttributes());

        $notice = Craft::t('blitz', 'Plugin settings saved.');
        $errors = [];

        if (!$purgerDriver->test()) {
            $errors[] = Craft::t('blitz', 'One or more purger connections failed.');
        }
        if (!$deployerDriver->test()) {
            $errors[] = Craft::t('blitz', 'One or more deployer connections failed.');
        }

        if (!empty($errors)) {
            Craft::$app->getSession()->setError($notice.' '.implode(' ', $errors));

            Craft::$app->getUrlManager()->setRouteParams($variables);

            return null;
        }

        Craft::$app->getSession()->setNotice($notice);

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
