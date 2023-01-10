<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\widgets;

use Craft;
use craft\base\Widget;
use craft\helpers\Html;
use putyourlightson\blitz\assets\BlitzAsset;
use putyourlightson\blitz\Blitz;

/**
 * @property-read string|null $title
 * @property-read string|null $bodyHtml
 */
class CacheWidget extends Widget
{
    /**
     * @inerhitdoc
     */
    public static function displayName(): string
    {
        return Craft::t('blitz', 'Blitz Cache');
    }

    /**
     * @inerhitdoc
     */
    protected static function allowMultipleInstances(): bool
    {
        return false;
    }

    /**
     * @inerhitdoc
     */
    public static function icon(): string
    {
        return Craft::getAlias('@putyourlightson/blitz/icon-mask.svg');
    }

    /**
     * @inerhitdoc
     */
    public static function maxColspan(): ?int
    {
        return 1;
    }

    public static function getActions(): array
    {
        $actions = [];
        $user = Craft::$app->getUser()->getIdentity();
        $iconPath = '@putyourlightson/blitz/resources/icons/';

        if ($user->can('blitz:refresh-urls')) {
            $actions[] = [
                'id' => 'refresh-urls',
                'label' => Craft::t('blitz', 'Refresh Cached URLs'),
                'instructions' => Craft::t('blitz', 'Refresh pages with specific URLs'),
                'icon' => Html::svg($iconPath . 'target.svg'),
            ];
        }

        if ($user->can('blitz:refresh-site') && Craft::$app->getIsMultiSite()) {
            $options = [];
            $sites = Craft::$app->getSites()->getAllSites();

            foreach ($sites as $site) {
                $options[$site->id] = $site->name;
            }

            $actions[] = [
                'id' => 'refresh-site',
                'label' => Craft::t('blitz', 'Refresh Site Cache'),
                'instructions' => Craft::t('blitz', 'Refresh all pages in a site'),
                'options' => $options,
                'icon' => Html::svg($iconPath . 'archive.svg'),
            ];
        }

        if ($user->can('blitz:refresh')) {
            $actions[] = [
                'id' => 'refresh',
                'label' => Craft::t('blitz', 'Refresh Entire Cache'),
                'instructions' => Craft::t('blitz', 'Refresh the entire cache'),
                'icon' => Html::svg($iconPath . 'refresh.svg'),
            ];
        }

        return $actions;
    }

    /**
     * @inerhitdoc
     */
    public function getBodyHtml(): ?string
    {
        Craft::$app->getView()->registerAssetBundle(BlitzAsset::class);

        return Craft::$app->getView()->renderTemplate('blitz/_widget', [
            'driverHtml' => Blitz::$plugin->cacheStorage->getWidgetHtml(),
            'actions' => static::getActions(),
        ]);
    }
}
