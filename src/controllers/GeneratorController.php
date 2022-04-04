<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\controllers\PreviewController;
use craft\web\Application;
use craft\web\Controller;
use craft\web\UrlManager;
use yii\base\Event;
use yii\web\Response;

/**
 * @since 4.0.0
 */
class GeneratorController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = true;

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Make sure a token was used to get here
        $this->requireToken();

        return parent::beforeAction($action);
    }

    /**
     * Generates a response and outputs whether it was successful.
     */
    public function actionGenerate()
    {
        $response = $this->_getResponse();

        // Suppress the output using a dummy stream
        Event::on(Response::class, Response::EVENT_AFTER_PREPARE,
            function(Event $event) {
                /** @var Response $response */
                $response = $event->sender;
                $response->stream = fn() => ['data' => ''];
            }
        );

        // No content will be sent, check the response code instead
        $response->send();
    }

    /**
     * Generates a response to a request URL.
     *
     * @see PreviewController::actionPreview()
     */
    private function _getResponse(): Response
    {
        // Recheck whether this is an action request, this time ignoring the token
        $this->request->checkIfActionRequest(true, false);

        // Re-route the request, this time ignoring the token
        /** @var Application $app */
        $app = Craft::$app;
        $urlManager = $app->getUrlManager();
        $urlManager->checkToken = false;
        $urlManager->setRouteParams([], false);
        $urlManager->setMatchedElement(null);

        return $app->handleRequest($this->request, true);
    }
}
