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
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected $allowAnonymous = true;

    // Public Methods
    // =========================================================================

    /**
     * Returns a rendered template.
     *
     * @return Response
     */
    public function actionGet(): Response
    {
        $template = Craft::$app->getRequest()->getRequiredParam('template');

        // Verify the template hash
        $template = Craft::$app->getSecurity()->validateData($template);

        if ($template === false) {
            throw new BadRequestHttpException('Request contained an invalid param.');
        }

        $params = Craft::$app->getRequest()->getParam('params', []);

        $output = Craft::$app->getView()->renderTemplate($template, $params);

        return $this->asRaw($output);
    }
}
