<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use craft\web\Controller;
use yii\web\Response;

/**
 * Used for testing purposes only.
 */
class TestController extends Controller
{
    protected int|bool|array $allowAnonymous = true;

    public function actionIndex(): ?Response
    {
        return null;
    }
}
