<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\Model;
use craft\helpers\ConfigHelper;
use craft\validators\DateTimeValidator;
use DateTime;
use putyourlightson\blitz\helpers\CacheTagHelper;

class CacheOptionsModel extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var bool
     */
    public $cachingEnabled = true;

    /**
     * @var bool
     */
    public $cacheElements = true;

    /**
     * @var bool
     */
    public $cacheElementQueries = true;

    /**
     * @var string[]|null
     */
    public $tags;

    /**
     * @var DateTime|null
     */
    public $expiryDate;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'cacheDuration':
                $this->cacheDuration($value);
                break;
            case 'tags':
                $this->tags($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['cachingEnabled', 'cacheElements', 'cacheElementQueries'], 'boolean'],
            [['expiryDate'], DateTimeValidator::class],
        ];
    }

    /**
     * @param bool
     *
     * @return static self reference
     */
    public function cachingEnabled(bool $value)
    {
        $this->cachingEnabled = $value;

        return $this;
    }

    /**
     * @param bool
     *
     * @return static self reference
     */
    public function cacheElements(bool $value)
    {
        $this->cacheElements = $value;

        return $this;
    }

    /**
     * @param bool
     *
     * @return static self reference
     */
    public function cacheElementQueries(bool $value)
    {
        $this->cacheElementQueries = $value;

        return $this;
    }

    /**
     * @param mixed
     *
     * @return static self reference
     */
    public function cacheDuration($value)
    {
        // Set default cache duration if greater than 0
        $cacheDuration = ConfigHelper::durationInSeconds($value);

        if ($cacheDuration > 0) {
            $cacheDuration += time();

            // Prepend with @ symbol to specify a timestamp
            $this->expiryDate = new DateTime('@'.$cacheDuration);
        }

        return $this;
    }

    /**
     * @param string|string[]|null
     *
     * @return static self reference
     */
    public function tags($value)
    {
        $this->tags = CacheTagHelper::getTags($value);

        return $this;
    }

    /**
     * @param DateTime|null
     *
     * @return static self reference
     */
    public function expiryDate($value)
    {
        $this->expiryDate = $value;

        return $this;
    }
}
