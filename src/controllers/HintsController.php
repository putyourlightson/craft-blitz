<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\web\Controller;
use putyourlightson\blitz\Blitz;
use yii\web\Response;

class HintsController extends Controller
{
    public function beforeAction($action): bool
    {
        $this->requirePermission('utility:blitz-hints');

        return parent::beforeAction($action);
    }

    /**
     * Clears all hints.
     */
    public function actionClearAll(): Response
    {
        $this->requirePostRequest();

        Blitz::$plugin->hints->clearAll();

        return $this->redirectToPostedUrl();
    }

    /**
     * Clears a specific hint.
     */
    public function actionClear(): Response
    {
        $this->requirePostRequest();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        Blitz::$plugin->hints->clear($id);

        return $this->redirectToPostedUrl();
    }
}
