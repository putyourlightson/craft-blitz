<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use craft\elements\GlobalSet;
use craft\elements\MatrixBlock;
use craft\helpers\App;
use putyourlightson\blitz\records\ElementCacheRecord;
use putyourlightson\blitz\records\ElementQueryCacheRecord;

class m181101_120000_delete_globalset_matrixblock_rows extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        App::maxPowerCaptain();

        // Delete element cache records
        $records = ElementCacheRecord::find()
            ->where(['type' => GlobalSet::class])
            ->orWhere(['type' => MatrixBlock::class])
            ->all();

        foreach ($records as $record) {
            $record->delete();
        }

        // Delete element query cache records
        $records = ElementQueryCacheRecord::find()
            ->where(['type' => GlobalSet::class])
            ->orWhere(['type' => MatrixBlock::class])
            ->all();

        foreach ($records as $record) {
            $record->delete();
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo self::class." cannot be reverted.\n";

        return false;
    }
}
