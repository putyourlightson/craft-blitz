<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\console\controllers;

use Craft;
use craft\console\Controller;
use putyourlightson\blitz\Blitz;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;

/**
 * Allows you to manage Blitz hints.
 */
class HintsController extends Controller
{
    /**
     * Clears all hints.
     *
     * @return int
     */
    public function actionClear(): int
    {
        $this->stdout(Craft::t('blitz', 'Clearing hints... '));
        Blitz::$plugin->hints->clearAll();
        $this->stdout(Craft::t('blitz', 'Done') . PHP_EOL, BaseConsole::FG_GREEN);

        return ExitCode::OK;
    }
}
