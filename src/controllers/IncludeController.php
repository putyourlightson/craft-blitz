<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\helpers\Json;
use craft\web\Controller;
use putyourlightson\blitz\Blitz;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class IncludeController extends Controller
{
    /**
     * @inheritdoc
     */
    protected int|bool|array $allowAnonymous = true;

    /**
     * Returns a rendered template using the cached include action.
     * This is necessary for detecting SSI requests and will only be hit when
     * no cached include exists.
     */
    public function actionCached(): Response
    {
        return $this->_getRenderedTemplate();
    }

    /**
     * Returns a dynamically rendered template.
     */
    public function actionDynamic(): Response
    {
        return $this->_getRenderedTemplate();
    }

    /**
     * Returns a rendered template.
     */
    public function _getRenderedTemplate(): Response
    {
        $index = Craft::$app->getRequest()->getRequiredParam('index');
        $include = Blitz::$plugin->cacheRequest->getIncludeByIndex($index);

        if ($include === null) {
            throw new BadRequestHttpException('Request contained an invalid param.');
        }

        $template = $include->template;

        if (!Craft::$app->getView()->resolveTemplate($template)) {
            throw new NotFoundHttpException('Template not found: ' . $template);
        }

        Craft::$app->getSites()->setCurrentSite($include->siteId);
        $params = Json::decodeIfJson($include->params);
        $output = Craft::$app->getView()->renderPageTemplate($template, $params);

        return $this->asRaw($output);
    }
}
