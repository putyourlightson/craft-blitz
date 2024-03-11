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
     * @var int|null
     */
    public ?int $line = null;

    /**
     * @var string[]
     */
    public array $stackTrace = [];

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateUpdated = null;
}
