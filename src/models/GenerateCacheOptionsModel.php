<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\Model;
use craft\validators\DateTimeValidator;

class GenerateCacheOptionsModel extends Model
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
     * @var string|null
     */
    public $flag = null;

    /**
     * @var \DateTime|null
     */
    public $expiryDate = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['cachingEnabled', 'cacheElements', 'cacheElementQueries'], 'boolean'],
            [['flag'], 'string'],
            [['expiryDate'], DateTimeValidator::class],
        ];
    }
}
