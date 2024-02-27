<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\models;

use craft\base\FieldInterface;
use craft\base\Model;
use DateTime;

class HintModel extends Model
{
    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var int|null
     */
    public ?int $fieldId = null;

    /**
     * @var FieldInterface|null
     */
    public ?FieldInterface $field = null;

    /**
     * @var string
     */
    public string $template = '';

    /**
     * @var string
     */
    public string $routeVariable = '';

    /**
     * @var int|null
     */
    public ?int $line = null;

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateUpdated = null;
}
