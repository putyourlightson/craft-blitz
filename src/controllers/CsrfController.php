<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\helpers\Html;
use craft\web\Controller;
use yii\web\Response;

class CsrfController extends Controller
{
    /**
     * @inheritdoc
     */
    protected int|bool|array $allowAnonymous = true;

    /**
     * Returns a CSRF input field.
     */
    public function actionInput(): Response
    {
        $request = Craft::$app->getRequest();
        $input = Html::hiddenInput($request->csrfParam, $request->getCsrfToken());

        return $this->asRaw($input);
    }

    /**
     * Returns the CSRF param.
     */
    public function actionParam(): Response
    {
        return $this->asRaw(Craft::$app->getRequest()->csrfParam);
    }

    /**
     * Returns a CSRF token.
     */
    public function actionToken(): Response
    {
        return $this->asRaw(Craft::$app->getRequest()->getCsrfToken());
    }

    /**
     * Returns all CSRF options in a single JSON response.
     */
    public function actionJson(): Response
    {
        return $this->asJson([
            'input' => $this->actionInput()->data,
            'param' => $this->actionParam()->data,
            'token' => $this->actionToken()->data,
        ]);
    }
}
