<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\Model;

class GenerateCacheOptionsModel extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var bool
     */
    public $cache = true;

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
            [['cache', 'cacheElements', 'cacheElementQueries'], 'bool'],
        ];
    }
}
