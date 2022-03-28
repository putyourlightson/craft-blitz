<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\controllers\PreviewController;
use craft\web\Controller;
use yii\web\Response;

class RevalidateController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = true;

    /**
     * Revalidates a URL.
     *
     * @see PreviewController::actionPreview()
     */
    public function actionRevalidate(): Response
    {
        // Make sure a token was used to get here
        $this->requireToken();

        // Recheck whether this is an action request, this time ignoring the token
        $this->request->checkIfActionRequest(true, false);

        // Re-route the request, this time ignoring the token
        $urlManager = Craft::$app->getUrlManager();
        $urlManager->checkToken = false;
        $urlManager->setRouteParams([], false);
        $urlManager->setMatchedElement(null);

        return Craft::$app->handleRequest($this->request, true);
    }
}
