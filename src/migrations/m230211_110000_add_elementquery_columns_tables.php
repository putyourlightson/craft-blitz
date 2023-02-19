<?php

namespace putyourlightson\blitz\migrations;

use Craft;
use craft\base\Element;
use craft\db\Migration;
use craft\elements\db\ElementQuery;
use craft\helpers\Json;
use craft\records\Field;
use putyourlightson\blitz\helpers\ElementQueryHelper;
use putyourlightson\blitz\helpers\ElementTypeHelper;
use putyourlightson\blitz\jobs\RefreshCacheJob;
use putyourlightson\blitz\records\ElementQueryAttributeRecord;
use putyourlightson\blitz\records\ElementQueryFieldRecord;
use putyourlightson\blitz\records\ElementQueryRecord;
use putyourlightson\blitz\records\ElementQuerySourceRecord;

class m230211_110000_add_elementquery_columns_tables extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->tableExists(ElementQuerySourceRecord::tableName())) {
            $this->dropTable(ElementQuerySourceRecord::tableName());
            $this->createTable(ElementQuerySourceRecord::tableName(), [
                'queryId' => $this->integer()->notNull(),
                'sourceId' => $this->integer()->notNull(),
                'PRIMARY KEY([[queryId]], [[sourceId]])',
            ]);

            $this->addForeignKey(null, ElementQuerySourceRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        }

        if (!$this->db->tableExists(ElementQueryAttributeRecord::tableName())) {
            $this->createTable(ElementQueryAttributeRecord::tableName(), [
                'queryId' => $this->integer()->notNull(),
                'attribute' => $this->string()->notNull(),
                'PRIMARY KEY([[queryId]], [[attribute]])',
            ]);

            $this->addForeignKey(null, ElementQueryAttributeRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
        }

        if (!$this->db->tableExists(ElementQueryFieldRecord::tableName())) {
            $this->createTable(ElementQueryFieldRecord::tableName(), [
                'queryId' => $this->integer()->notNull(),
                'fieldId' => $this->integer()->notNull(),
                'PRIMARY KEY([[queryId]], [[fieldId]])',
            ]);

            $this->addForeignKey(null, ElementQueryFieldRecord::tableName(), 'queryId', ElementQueryRecord::tableName(), 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, ElementQueryFieldRecord::tableName(), 'fieldId', Field::tableName(), 'id', 'CASCADE', 'CASCADE');
        }

        /** @var ElementQueryRecord[] $elementQueryRecords */
        $elementQueryRecords = ElementQueryRecord::find()->all();
        foreach ($elementQueryRecords as $elementQueryRecord) {
            $this->_populateTables($elementQueryRecord);
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

    /**
     * Populates the tables from element queries.
     *
     * @see RefreshCacheJob::_populateCacheIdsFromElementQueryRecord()
     */
    private function _populateTables(ElementQueryRecord $elementQueryRecord): void
    {
        // Ensure class still exists as a plugin may have been removed since being saved
        if (!class_exists($elementQueryRecord->type)) {
            return;
        }

        /** @var Element $elementType */
        $elementType = $elementQueryRecord->type;

        /** @var ElementQuery $elementQuery */
        $elementQuery = $elementType::find();

        $params = Json::decodeIfJson($elementQueryRecord->params);

        // If json decode failed
        if (!is_array($params)) {
            return;
        }

        foreach ($params as $key => $val) {
            $elementQuery->{$key} = $val;
        }

        $db = Craft::$app->getDb();

        $sourceIdAttribute = ElementTypeHelper::getSourceIdAttribute($elementType);
        $sourceIds = $params[$sourceIdAttribute] ?? [];
        $sourceIds = is_array($sourceIds) ? [$sourceIds] : $sourceIds;

        $values = [];
        foreach ($sourceIds as $sourceId) {
            $values[] = [$elementQueryRecord->id, $sourceId];
        }

        $db->createCommand()
            ->batchInsert(
                ElementQuerySourceRecord::tableName(),
                ['queryId', 'sourceId'],
                $values,
            )
            ->execute();

        $attributes = ElementQueryHelper::getElementQueryAttributes($elementQuery);

        $values = [];
        foreach ($attributes as $attribute) {
            $values[] = [$elementQueryRecord->id, $attribute];
        }

        $db->createCommand()
            ->batchInsert(
                ElementQueryAttributeRecord::tableName(),
                ['queryId', 'attribute'],
                $values,
            )
            ->execute();

        $fieldIds = ElementQueryHelper::getElementQueryFieldIds($elementQuery);

        $values = [];
        foreach ($fieldIds as $fieldId) {
            $values[] = [$elementQueryRecord->id, $fieldId];
        }

        $db->createCommand()
            ->batchInsert(
                ElementQueryFieldRecord::tableName(),
                ['queryId', 'fieldId'],
                $values,
            )
            ->execute();
    }
}
