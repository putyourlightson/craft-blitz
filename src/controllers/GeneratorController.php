<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\controllers\PreviewController;
use craft\helpers\UrlHelper;
use craft\web\Application;
use craft\web\Controller;
use craft\web\twig\variables\Paginate;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\SiteUriHelper;
use Throwable;
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
    public function actionGenerate(): ?Response
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
    private function _generateResponse(): ?Response
    {
        // Remove the token query param.
        $tokenParam = Craft::$app->getConfig()->getGeneral()->tokenParam;
        $queryParams = $this->request->getQueryParams();

        if (isset($queryParams[$tokenParam])) {
            unset($queryParams[$tokenParam]);
            $this->request->setQueryParams($queryParams);
        }

        /**
         * Update the query string to avoid the token being added to URLs.
         *
         * @see Paginate::getPageUrl()
         */
        $_SERVER['QUERY_STRING'] = http_build_query($queryParams);

        /**
         * Unset the token to avoid it being added to URLs.
         *
         * @see UrlHelper::_createUrl()
         */
        $this->request->setToken(null);

        // Recheck whether this is an action request, this time ignoring the token
        $this->request->checkIfActionRequest(true, false);

        // Re-route the request, this time ignoring the token
        /** @var Application $app */
        $app = Craft::$app;
        $urlManager = $app->getUrlManager();
        $urlManager->checkToken = false;
        $urlManager->setRouteParams([], false);
        $urlManager->setMatchedElement(null);

        try {
            $response = $app->handleRequest($this->request, true);
        } catch (Throwable) {
            $response = null;
        }

        // If the response failed, delete the cached value
        // https://github.com/putyourlightson/craft-blitz/issues/483
        if ($response === null || !$response->getIsOk()) {
            $siteUri = SiteUriHelper::getSiteUriFromRequest($this->request);
            if ($siteUri !== null) {
                Blitz::$plugin->cacheStorage->deleteUris([$siteUri]);
            }
        }

        return $response;
    }
}
