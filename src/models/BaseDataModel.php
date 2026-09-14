<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\Model;

/**
 * Base class for storing, manipulating and returning data. Child classes provide
 * API methods that help avoid managing complex arrays and ensuring values are
 * unique.
 *
 * @since 4.4.0
 */
abstract class BaseDataModel extends Model
{
    /**
     * Site ID used when any site may be affected.
     *
     * @since 5.13.0
     */
    public const SITE_ID_ANY = 0;

    /**
     * @since 5.13.0
     */
    protected const SITE_IDS_ANY = [self::SITE_ID_ANY => true];

    /**
     * @var array
     */
    public array $data = [];

    /**
     * @var array
     */
    private array $initialData = [];

    public function init(): void
    {
        parent::init();

        $this->initialData = $this->data;
    }

    public function isEmpty(): bool
    {
        return $this->data === $this->initialData;
    }

    protected function getKeysAsValues(array $indexes): array
    {
        $keys = $this->data;

        foreach ($indexes as $index) {
            $keys = $keys[$index] ?? [];
        }

        return array_keys($keys);
    }
}
