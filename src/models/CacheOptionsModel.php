<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use Craft;
use craft\base\Model;
use craft\helpers\ConfigHelper;
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
     * @var bool Whether elements should be tracked in the database.
     */
    public bool $trackElements = true;

    /**
     * @var bool Whether element queries should be tracked in the database.
     */
    public bool $trackElementQueries = true;

    /**
     * @var string[]|string|bool Whether and which custom fields should be tracked.
     */
    public array|string|bool $trackCustomFields = true;

    /**
     * @var bool
     * @deprecated in 4.4.0. Use [[$trackElements]] instead.
     */
    public bool $cacheElements = true;

    /**
     * @var bool
     * @deprecated in 4.4.0. Use [[$trackElementQueries]] instead.
     */
    public bool $cacheElementQueries = true;

    /**
     * @var int|bool
     */
    public int|bool $outputComments = true;

    /**
     * @var string[]|string
     */
    public array|string $tags = [];

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
    public function __set($name, $value): void
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
     * Sets the track elements option.
     */
    public function trackElements(bool $value): self
    {
        $this->trackElements = $value;

        return $this;
    }

    /**
     * Sets the track element queries option.
     */
    public function trackElementQueries(bool $value): self
    {
        $this->trackElementQueries = $value;

        return $this;
    }

    /**
     * Sets the track custom fields option.
     */
    public function trackCustomFields(array|string|bool $value = true): self
    {
        $this->trackCustomFields = $value;

        return $this;
    }

    /**
     * Sets the cache elements option.
     *
     * @deprecated in 4.4.0. Use [[trackElements()]] instead.
     */
    public function cacheElements(bool $value): self
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`craft.blitz.options.cacheElements()` has been deprecated. Use `craft.blitz.options.trackElements()` instead.');

        $this->trackElements = $value;

        return $this;
    }

    /**
     * Sets the cache element queries option.
     *
     * @deprecated in 4.4.0. Use [[trackElementQueries()]] instead.
     */
    public function cacheElementQueries(bool $value): self
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`craft.blitz.options.cacheElementQueries()` has been deprecated. Use `craft.blitz.options.trackElementQueries()` instead.');

        $this->trackElementQueries = $value;

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
            $this->expiryDate = new DateTime('@' . $timestamp);
        }

        return $this;
    }

    /**
     * Sets the tags option.
     */
    public function tags(array|string|null $value): self
    {
        $this->tags = $value;

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

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['cachingEnabled', 'cacheElements', 'cacheElementQueries'], 'boolean'],
            [['paginate'], 'integer'],
            [['expiryDate'], DateTimeValidator::class],
        ];
    }
}
