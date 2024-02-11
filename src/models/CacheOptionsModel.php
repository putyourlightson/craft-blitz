<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\Model;
use craft\helpers\ConfigHelper;
use craft\validators\DateTimeValidator;
use DateTime;

class CacheOptionsModel extends Model
{
    /**
     * Whether caching is enabled.
     */
    public bool $cachingEnabled = true;

    /**
     * Whether elements should be tracked in the database.
     *
     * @since 4.4.0
     */
    public bool $trackElements = true;

    /**
     * Whether element queries should be tracked in the database.
     *
     * @since 4.4.0
     */
    public bool $trackElementQueries = true;

    /**
     * Whether the "cached on" and "served by" timestamp comments should be appended to the cached output.
     *
     * @see SettingsModel::outputComments
     */
    public int|bool|null $outputComments = null;

    /**
     * One or more tags (array or string separated by commas) to associate with this page.
     *
     * @var string[]|string
     */
    public array|string $tags = [];

    /**
     * Mark the current page for pagination of the specified number of pages.
     */
    public ?int $paginate = null;

    /**
     * When the cache should expire.
     */
    public ?DateTime $expiryDate = null;

    /**
     * @var int|null
     */
    private ?int $cacheDuration = null;

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
        return $this->cacheDuration;
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
     *
     * @since 4.4.0
     */
    public function trackElements(bool $value): self
    {
        $this->trackElements = $value;

        return $this;
    }

    /**
     * Sets the track element queries option.
     *
     * @since 4.4.0
     */
    public function trackElementQueries(bool $value): self
    {
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
            $this->cacheDuration = $cacheDuration;

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
