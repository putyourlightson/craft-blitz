<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\controllers\PreviewController;
use craft\web\Controller;
use JetBrains\PhpStorm\NoReturn;
use yii\base\Event;
use yii\web\Response;

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
    #[NoReturn] public function actionGenerate()
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

        $response->send();

        exit($response->getIsOk() ? '1' : '0');
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
        $urlManager = Craft::$app->getUrlManager();
        $urlManager->checkToken = false;
        $urlManager->setRouteParams([], false);
        $urlManager->setMatchedElement(null);

        return Craft::$app->handleRequest($this->request, true);
    }
}
