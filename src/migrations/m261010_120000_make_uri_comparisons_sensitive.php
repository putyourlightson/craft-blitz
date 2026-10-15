<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitz\records\CacheRecord;

class m261010_120000_make_uri_comparisons_sensitive extends Migration
{
    public function safeUp(): bool
    {
        $table = CacheRecord::tableName();
        $tableSchema = $this->db->getTableSchema($table, true);
        $column = $tableSchema?->columns['uri'] ?? null;

        if ($column === null) {
            return true;
        }

        if ($this->db->getIsMysql()) {
            $metadata = $this->db->createCommand(
                'SELECT CHARACTER_SET_NAME AS charset, COLLATION_NAME AS collation
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = COALESCE(:schema, DATABASE())
                AND TABLE_NAME = :table AND COLUMN_NAME = :column',
                [':schema' => $tableSchema->schemaName, ':table' => $tableSchema->name, ':column' => 'uri'],
            )->queryOne();
            if (!$metadata) {
                return false;
            }

            // Binary columns already compare bytes and have no charset or collation.
            if ($metadata['charset'] === null || $metadata['collation'] === null) {
                return true;
            }

            $currentCollation = $metadata['collation'];
            $charset = $metadata['charset'];
            $collation = $charset === 'binary' ? 'binary' : $charset . '_bin';
            $quotedCollation = $collation;
        } elseif ($this->db->getIsPgsql()) {
            // Yii's PostgreSQL column metadata does not include the collation.
            // The table name is bound as a parameter, so it must be the resolved
            // name: the `{{%table}}` placeholder is only replaced in the SQL itself.
            $currentCollation = $this->db->createCommand(
                'SELECT c.collname FROM pg_catalog.pg_attribute a
                INNER JOIN pg_catalog.pg_collation c ON c.oid = a.attcollation
                WHERE a.attrelid = CAST(:table AS regclass) AND a.attname = :column',
                [':table' => $this->db->quoteTableName($tableSchema->fullName), ':column' => 'uri'],
            )->queryScalar();
            if (!$currentCollation) {
                return false;
            }

            $collation = 'C';
            $quotedCollation = '"C"';
        } else {
            return true;
        }

        if ($currentCollation === $collation) {
            return true;
        }

        // Preserve the installed column length while making URI identity explicit.
        $type = $this->string($column->size)
            ->append('COLLATE ' . $quotedCollation . ' NOT NULL');
        $this->alterColumn($table, 'uri', $type);

        return true;
    }

    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return false;
    }
}
