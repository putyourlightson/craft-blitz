<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\controllers\PreviewController;
use craft\web\Application;
use craft\web\Controller;
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
     * Generates and returns a response with the output suppressed.
     */
    public function actionGenerate(): Response
    {
        $response = $this->_generateResponse();

        // Suppress the output using a dummy stream
        Event::on(Response::class, Response::EVENT_AFTER_PREPARE,
            function(Event $event) {
                /** @var Response $response */
                $response = $event->sender;
                $response->stream = fn() => ['data' => ''];
            }
        );

        // No content will be returned, the response code should be checked instead
        return $response;
    }

    /**
     * Generates a response to a request URL.
     *
     * @see PreviewController::actionPreview()
     */
    private function _generateResponse(): Response
    {
        // Remove the token query param
        $tokenParam = Craft::$app->config->general->tokenParam;
        $queryParams = $this->request->getQueryParams();

        if (isset($queryParams[$tokenParam])) {
            unset($queryParams[$tokenParam]);
            $this->request->setQueryParams($queryParams);
        }

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
