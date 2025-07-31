<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\helpers\Json;
use craft\web\Controller;
use craft\web\View;
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
    public function actionCached(string $index): Response
    {
        return $this->getRenderedTemplate($index);
    }

    /**
     * Returns a dynamically rendered template.
     */
    public function actionDynamic(string $index): Response
    {
        return $this->getRenderedTemplate($index);
    }

    /**
     * Returns a rendered template.
     */
    public function getRenderedTemplate(string $index): Response
    {
        $include = Blitz::$plugin->cacheRequest->getIncludeByIndex($index);

        if ($include === null) {
            throw new BadRequestHttpException('Request contained an invalid param.');
        }

        $template = $include->template;

        if (!Craft::$app->getView()->resolveTemplate($template, View::TEMPLATE_MODE_SITE)) {
            throw new NotFoundHttpException('Template not found: ' . $template);
        }

        Craft::$app->getSites()->setCurrentSite($include->siteId);
        $params = Json::decodeIfJson($include->params);
        $output = Craft::$app->getView()->renderPageTemplate($template, $params, View::TEMPLATE_MODE_SITE);

        return $this->asRaw($output);
    }
}
