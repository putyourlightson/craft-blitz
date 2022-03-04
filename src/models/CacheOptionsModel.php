<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\Model;
use craft\helpers\ConfigHelper;
use craft\helpers\StringHelper;
use craft\validators\DateTimeValidator;
use DateTime;

/**
 * @property-read null|int $cacheDuration
 */
class CacheOptionsModel extends Model
{
    /**
     * @var bool
     */
    public bool $cachingEnabled = true;

    /**
     * @var bool
     */
    public bool $cacheElements = true;

    /**
     * @var bool
     */
    public bool $cacheElementQueries = true;

    /**
     * @var int|bool
     */
    public int|bool $outputComments = true;

    /**
     * @var string[]
     */
    public array $tags = [];

    /**
     * @var int|null
     */
    public ?int $paginate = null;

    /**
     * @var DateTime|null
     */
    public ?DateTime $expiryDate = null;

    /**
     * @var int|null
     */
    private ?int $_cacheDuration = null;

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
    public function attributes(): array
    {
        $names = parent::attributes();

        return array_merge($names, ['cacheDuration']);
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        return [
            [['cachingEnabled', 'cacheElements', 'cacheElementQueries'], 'boolean'],
            [['paginate'], 'integer'],
            [['expiryDate'], DateTimeValidator::class],
        ];
    }

    /**
     * Returns the cache duration option.
     */
    public function getCacheDuration(): ?int
    {
        return $this->_cacheDuration;
    }

    /**
     * Sets the caching enabled option.
     */
    public function cachingEnabled(bool $value): self
    {
        $this->cachingEnabled = $value;

        return $this;
    }

    /**
     * Sets the cache elements option.
     */
    public function cacheElements(bool $value): self
    {
        $this->cacheElements = $value;

        return $this;
    }

    /**
     * Sets the cache element queries option.
     */
    public function cacheElementQueries(bool $value): self
    {
        $this->cacheElementQueries = $value;

        return $this;
    }

    /**
     * Sets the output comments option.
     */
    public function outputComments(bool|int $value): self
    {
        $this->outputComments = $value;

        return $this;
    }

    /**
     * Sets the cache duration option.
     */
    public function cacheDuration(mixed $value): self
    {
        // Set cache duration if greater than 0 seconds
        $cacheDuration = ConfigHelper::durationInSeconds($value);

        if ($cacheDuration > 0) {
            $this->_cacheDuration = $cacheDuration;

            $timestamp = $cacheDuration + time();

            // Prepend with @ symbol to specify a timestamp
            $this->expiryDate = new DateTime('@'.$timestamp);
        }

        return $this;
    }

    /**
     * Sets the tags option.
     */
    public function tags(array|string|null $value): self
    {
        $this->tags = is_string($value) ? StringHelper::split($value) : $value;

        return $this;
    }

    /**
     * Sets the paginate option.
     */
    public function paginate(int $value = null): self
    {
        $this->paginate = $value;

        return $this;
    }

    /**
     * Sets the expiry date option.
     */
    public function expiryDate(DateTime $value = null): self
    {
        $this->expiryDate = $value;

        return $this;
    }
}
