<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\Model;

/**
 * Base class for storing, manipulating and returning data. Child classes provide
 * API methods that helps avoid managing complex arrays and ensuring values are
 * unique.
 */
abstract class BaseDataModel extends Model
{
    /**
     * @var array
     */
    public array $data = [];

    protected function getKeysAsValues(array $indexes): array
    {
        $keys = $this->data;

        foreach ($indexes as $index) {
            $keys = $keys[$index] ?? [];
        }

        return array_keys($keys);
    }
}
