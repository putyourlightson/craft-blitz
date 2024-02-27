<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\utilities;

use Craft;
use craft\base\Utility;
use putyourlightson\blitz\assets\BlitzAsset;
use putyourlightson\blitz\Blitz;
use putyourlightson\sprig\Sprig;

class HintsUtility extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Blitz Hints';
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'blitz-hints';
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        $iconPath = Craft::getAlias('@putyourlightson/blitz/resources/icons/hints.svg');

        if (!is_string($iconPath)) {
            return null;
        }

        return $iconPath;
    }

    /**
     * @inheritdoc
     */
    public static function badgeCount(): int
    {
        return Blitz::$plugin->hints->getTotalWithoutRouteVariables();
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        Sprig::bootstrap();
        Sprig::$core->components->setConfig(['requestClass' => 'loading']);

        Craft::$app->getView()->registerAssetBundle(BlitzAsset::class);

        return Craft::$app->getView()->renderTemplate('blitz/_utilities/hints', [
            'hints' => Blitz::$plugin->hints->getAll(),
            'hasRouteVariables' => Blitz::$plugin->hints->hasRouteVariables(),
        ]);
    }
}
