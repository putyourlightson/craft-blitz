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
 * @property-read HintModel[] $all
 * @property-read int $total
 * @property-read int $totalWithoutRouteVariables
 */
class HintsHelper extends Component
{
    /**
     * Returns the total hints.
     */
    public function getTotal(): int
    {
        return HintRecord::find()->count();
    }

    /**
     * Returns the total hints without route variables.
     */
    public function getTotalWithoutRouteVariables(): int
    {
        return HintRecord::find()
            ->where(['routeVariable' => ''])
            ->count();
    }

    /**
     * Returns whether there are hints with route variables.
     */
    public function hasRouteVariables(): bool
    {
        return HintRecord::find()
            ->where(['not', ['routeVariable' => '']])
            ->exists();
    }

    /**
     * Gets all hints.
     *
     * @return HintModel[]
     */
    public function getAll(): array
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
