<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\base\Component;
use putyourlightson\blitz\models\HintModel;
use putyourlightson\blitz\records\HintRecord;

/**
 * @property-read int $count
 * @property-read HintModel[] $all
 */
class HintsHelper extends Component
{
    /**
     * Returns the total number of hints.
     */
    public static function getCount(): int
    {
        return HintRecord::find()->count();
    }

    /**
     * Gets all hints.
     *
     * @return HintModel[]
     */
    public static function getAll(): array
    {
        $hints = [];

        /** @var HintRecord[] $hintRecords */
        $hintRecords = HintRecord::find()
            ->orderBy(['dateUpdated' => SORT_DESC])
            ->all();

        foreach ($hintRecords as $record) {
            $hint = new HintModel();
            $hint->setAttributes($record->getAttributes(), false);

            $field = Craft::$app->getFields()->getFieldById($hint->fieldId);
            if ($field) {
                $hint->field = $field;
                $hints[] = $hint;
            }
        }

        return $hints;
    }
}
