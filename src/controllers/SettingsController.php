<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\base\ComponentInterface;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\deployers\BaseDeployer;
use putyourlightson\blitz\drivers\generators\BaseCacheGenerator;
use putyourlightson\blitz\drivers\purgers\BaseCachePurger;
use putyourlightson\blitz\drivers\storage\BaseCacheStorage;
use putyourlightson\blitz\helpers\BaseDriverHelper;
use putyourlightson\blitz\helpers\CacheGeneratorHelper;
use putyourlightson\blitz\helpers\CachePurgerHelper;
use putyourlightson\blitz\helpers\CacheStorageHelper;
use putyourlightson\blitz\helpers\DeployerHelper;
use yii\web\Response;

class SettingsController extends Controller
{
    /**
     * @inerhitdoc
     */
    public function beforeAction($action): bool
    {
        $this->requireAdmin();

        return parent::beforeAction($action);
    }

    /**
     * Edit the plugin settings.
     */
    public function actionEdit(): ?Response
    {
        $settings = Blitz::$plugin->settings;

        // Get site options
        $siteOptions = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteOptions[] = [
                'value' => $site->id,
                'label' => $site->name,
            ];
        }

        /** @var BaseCacheStorage $storageDriver */
        $storageDriver = BaseDriverHelper::createDriver(
            $settings->cacheStorageType,
            $settings->cacheStorageSettings
        );

        // Validate the driver so that any errors will be displayed
        $storageDriver->validate();

        $storageDrivers = CacheStorageHelper::getAllDrivers();

        /** @var BaseCacheGenerator $generatorDriver */
        $generatorDriver = BaseDriverHelper::createDriver(
            $settings->cacheGeneratorType,
            $settings->cacheGeneratorSettings,
        );

        // Validate the generator so that any errors will be displayed
        $generatorDriver->validate();

        $generatorDrivers = CacheGeneratorHelper::getAllDrivers();

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

        $detectSsiTag = null;
        if (Blitz::$plugin->settings->detectSsiEnabled) {
            // SSI URIs only work with an `action` parameter.
            $uri = UrlHelper::rootRelativeUrl(
                UrlHelper::cpUrl('', ['action' => 'blitz/settings/detect-ssi'])
            );
            $detectSsiTag = Template::raw(Blitz::$plugin->settings->getSsiTag($uri));
        }

        return $this->renderTemplate('blitz/_settings', [
            'settings' => $settings,
            'config' => Craft::$app->getConfig()->getConfigFromFile('blitz'),
            'siteOptions' => $siteOptions,
            'storageDriver' => $storageDriver,
            'storageDrivers' => $storageDrivers,
            'storageTypeOptions' => array_map([$this, '_getSelectOption'], $storageDrivers),
            'generatorDriver' => $generatorDriver,
            'generatorDrivers' => $generatorDrivers,
            'generatorTypeOptions' => array_map([$this, '_getSelectOption'], $generatorDrivers),
            'purgerDriver' => $purgerDriver,
            'purgerDrivers' => $purgerDrivers,
            'purgerTypeOptions' => array_map([$this, '_getSelectOption'], $purgerDrivers),
            'deployerDriver' => $deployerDriver,
            'deployerDrivers' => $deployerDrivers,
            'deployerTypeOptions' => array_map([$this, '_getSelectOption'], $deployerDrivers),
            'detectSsiTag' => $detectSsiTag,
        ]);
    }

    /**
     * Saves the plugin settings.
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $postedSettings = $request->getBodyParam('settings', []);
        $storageSettings = $request->getBodyParam('cacheStorageSettings', []);
        $generatorSettings = $request->getBodyParam('cacheGeneratorSettings', []);
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

        // Apply generator settings excluding type
        $settings->cacheGeneratorSettings = $generatorSettings[$settings->cacheGeneratorType] ?? [];

        // Create the generator driver so that we can validate it
        /** @var BaseCacheGenerator $generatorDriver */
        $generatorDriver = BaseDriverHelper::createDriver(
            $settings->cacheGeneratorType,
            $settings->cacheGeneratorSettings,
        );

        // Apply purger settings excluding type
        $settings->cachePurgerSettings = $purgerSettings[$settings->cachePurgerType] ?? [];

        // Create the purger driver so that we can validate it
        /** @var BaseCachePurger $purgerDriver */
        $purgerDriver = BaseDriverHelper::createDriver(
            $settings->cachePurgerType,
            $settings->cachePurgerSettings
        );

        // Apply deployer settings excluding type
        $settings->deployerSettings = $deployerSettings[$settings->deployerType] ?? [];

        // Create the deployer driver so that we can validate it
        /** @var BaseDeployer $deployerDriver */
        $deployerDriver = BaseDriverHelper::createDriver(
            $settings->deployerType,
            $settings->deployerSettings
        );

        // Validate
        $settings->validate();
        $storageDriver->validate();
        $generatorDriver->validate();
        $purgerDriver->validate();
        $deployerDriver->validate();

        if ($settings->hasErrors()
            || $storageDriver->hasErrors()
            || $generatorDriver->hasErrors()
            || $purgerDriver->hasErrors()
            || $deployerDriver->hasErrors()
        ) {
            Craft::$app->getSession()->setError(Craft::t('blitz', 'Couldn’t save plugin settings.'));

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
            Craft::$app->getSession()->setError($notice . ' ' . implode(' ', $errors));

            return null;
        }

        Craft::$app->getSession()->setNotice($notice);

        return $this->redirectToPostedUrl();
    }

    /**
     * Returns an SSI detected message.
     */
    public function actionDetectSsi(): string
    {
        return '<script>blitzSsiDetected = true;</script>';
    }

    /**
     * Gets select option from a component.
     */
    private function _getSelectOption(ComponentInterface $component): array
    {
        return [
            'value' => $component::class,
            'label' => $component::displayName(),
        ];
    }
}
