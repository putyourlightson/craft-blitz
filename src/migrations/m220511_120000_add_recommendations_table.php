<?php

namespace putyourlightson\blitz\migrations;

use craft\db\Migration;
use putyourlightson\blitzrecommendations\migrations\Install as InstallCore;

class m220511_120000_add_recommendations_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $migration = new InstallCore();

        return $migration->safeUp();
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $migration = new InstallCore();

        return $migration->safeDown();
    }
}
