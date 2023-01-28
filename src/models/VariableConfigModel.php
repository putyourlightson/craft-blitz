<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\Model;

class VariableConfigModel extends Model
{
    /**
     * @var string
     */
    public string $requestType = 'ajax';

    /**
     * @var string
     */
    public string $wrapperElement = 'span';

    /**
     * @var string
     */
    public string $placeholder = '';

    /**
     * @var string
     */
    public string $property = '';

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['requestType', 'wrapperElement', 'placeholder', 'property'], 'string'],
            [['requestType'], 'in', 'range' => ['ajax', 'include']],
        ];
    }
}
