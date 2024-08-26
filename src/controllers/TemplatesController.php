<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\web\Controller;
use craft\web\View;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class TemplatesController extends Controller
{
    /**
     * @inheritdoc
     */
    protected int|bool|array $allowAnonymous = true;

    /**
     * Returns a rendered template.
     *
     * @deprecated in 4.3.0
     */
    public function actionGet(): Response
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`blitz/templates/get` has been deprecated.');

        $template = Craft::$app->getRequest()->getRequiredParam('template');

        // Verify the template hash
        $template = Craft::$app->getSecurity()->validateData($template);

        if ($template === false) {
            throw new BadRequestHttpException('Request contained an invalid param.');
        }

        $siteId = Craft::$app->getRequest()->getParam('siteId');

        if ($siteId !== null) {
            Craft::$app->getSites()->setCurrentSite($siteId);
        }

        $params = Craft::$app->getRequest()->getParam('params', []);
        $output = Craft::$app->getView()->renderPageTemplate($template, $params, View::TEMPLATE_MODE_SITE);

        return $this->asRaw($output);
    }
}
