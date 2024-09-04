<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use craft\db\Table;
use putyourlightson\blitz\helpers\ElementTypeHelper;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryRecord;

class m240110_120000_clear_out_legacy_cached_element_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Delete records in batches to help avoid memory limit issues.
        // https://github.com/putyourlightson/craft-blitz/issues/708
        $batchSize = 10000;

        // Element caches
        $elementTypes = ElementCacheRecord::find()
            ->select(['type'])
            ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[elementId]]')
            ->groupBy(['type'])
            ->column();

        foreach ($elementTypes as $elementType) {
            if (!ElementTypeHelper::getIsCacheableElementType($elementType)) {
                $done = false;
                while (!$done) {
                    $legacyElementIds = ElementCacheRecord::find()
                        ->select(['elementId'])
                        ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[elementId]]')
                        ->where(['type' => $elementType])
                        ->limit($batchSize)
                        ->column();

                    if (empty($legacyElementIds)) {
                        $done = true;
                    } else {
                        ElementCacheRecord::deleteAll(['elementId' => $legacyElementIds]);
                    }
                }
            }
        }

        // Element query caches
        $elementTypes = ElementQueryRecord::find()
            ->select(['type'])
            ->groupBy(['type'])
            ->column();

        foreach ($elementTypes as $elementType) {
            if (!ElementTypeHelper::getIsCacheableElementType($elementType)) {
                $done = false;
                while (!$done) {
                    $legacyElementQueryIds = ElementQueryRecord::find()
                        ->select(['id'])
                        ->where(['type' => $elementType])
                        ->limit($batchSize)
                        ->column();

                    if (empty($legacyElementQueryIds)) {
                        $done = true;
                    } else {
                        ElementQueryRecord::deleteAll(['id' => $legacyElementQueryIds]);
                    }
                }
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return true;
    }
}
