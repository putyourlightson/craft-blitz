<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use craft\base\SavableComponentInterface;
use craft\errors\MissingComponentException;
use craft\helpers\Component;
use yii\base\InvalidConfigException;

class BaseDriverHelper
{
    // Static
    // =========================================================================

    /**
     * Creates drivers of the provided types.
     *
     * @param array $types
     *
     * @return SavableComponentInterface[]
     */
    public static function createDrivers(array $types): array
    {
        $drivers = [];

        /** @var SavableComponentInterface $type */
        foreach ($types as $type) {
            if ($type::isSelectable()) {
                $driver = self::createDriver($type);

                if ($driver !== null) {
                    $drivers[] = $driver;
                }
            }
        }

        return $drivers;
    }

    /**
     * Creates a driver of the provided type with the optional settings.
     *
     * @param string $type
     * @param array|null $settings
     *
     * @return SavableComponentInterface|null
     */
    public static function createDriver(string $type, array $settings = [])
    {
        $driver = null;

        try {
            $driver = Component::createComponent([
                'type' => $type,
                'settings' => $settings,
            ]);
        }
        catch (InvalidConfigException $e) {}
        catch (MissingComponentException $e) {}

        return $driver;
    }
}
