<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class TemplatesController extends Controller
{
    /**
     * @inheritdoc
     */
    protected int|bool|array $allowAnonymous = true;

    /**
     * Returns a rendered template using the static include action (necessary for detecting SSI requests).
     */
    public function actionStaticInclude(): Response
    {
        return $this->_getRenderedTemplate();
    }

    /**
     * Returns a rendered template.
     */
    public function actionDynamicInclude(): Response
    {
        return $this->_getRenderedTemplate();
    }

    /**
     * Returns a rendered template.
     *
     * @deprecated in 4.3.0. Use [[blitz/templates/static-include]] or [[blitz/templates/dynamic-include] instead.
     */
    public function actionGet(): Response
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`blitz/templates/get` has been deprecated. Use `blitz/templates/static-include` or `blitz/templates/dynamic-include` instead.');

        return $this->_getRenderedTemplate();
    }

    /**
     * Returns a rendered template.
     */
    private function _getRenderedTemplate(): Response
    {
        $template = Craft::$app->getRequest()->getRequiredParam('template');

        // Verify the template hash
        $template = Craft::$app->getSecurity()->validateData($template);

        if ($template === false) {
            throw new BadRequestHttpException('Request contained an invalid param.');
        }

        if (!Craft::$app->getView()->resolveTemplate($template)) {
            throw new NotFoundHttpException('Template not found: ' . $template);
        }

        $siteId = Craft::$app->getRequest()->getParam('siteId');

        if ($siteId !== null) {
            Craft::$app->getSites()->setCurrentSite($siteId);
        }

        $params = Craft::$app->getRequest()->getParam('params', []);
        $output = Craft::$app->getView()->renderPageTemplate($template, $params);

        return $this->asRaw($output);
    }
}
