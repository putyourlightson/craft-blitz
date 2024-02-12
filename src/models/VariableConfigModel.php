<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\Model;

class VariableConfigModel extends Model
{
    /**
     * @const string Specifies that the request should be AJAX.
     */
    public const AJAX_REQUEST_TYPE = 'ajax';

    /**
     * @const string Specifies that the request should be a Server-Side or Edge-Side Include.
     */
    public const INCLUDE_REQUEST_TYPE = 'include';

    /**
     * @const string Specifies that the request should output cached content inline.
     */
    public const INLINE_REQUEST_TYPE = 'inline';

    /**
     * @var string Specifies the request type to use.
     */
    public string $requestType = 'ajax';

    /**
     * @var string Specifies the wrapper element type to use.
     */
    public string $wrapperElement = 'span';

    /**
     * @var string Specifies the class to add to the wrapper element.
     */
    public string $wrapperClass = '';

    /**
     * @var string Specifies the placeholder content.
     */
    public string $placeholder = '';

    /**
     * @var string Specifies the property to place on the wrapper element.
     */
    public string $property = '';

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['requestType', 'wrapperElement', 'wrapperClass', 'placeholder', 'property'], 'string'],
            [['requestType'], 'in', 'range' => [self::AJAX_REQUEST_TYPE, self::INCLUDE_REQUEST_TYPE]],
        ];
    }
}
