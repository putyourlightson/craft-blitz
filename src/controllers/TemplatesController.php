<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\web\Controller;
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
     */
    public function actionGet(): Response
    {
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
        $output = Craft::$app->getView()->renderPageTemplate($template, $params);

        return $this->asRaw($output);
    }
}
