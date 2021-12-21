<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

class CsrfController extends Controller
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
     * Returns a CSRF input field.
     *
     * @return Response
     */
    public function actionInput(): Response
    {
        $request = Craft::$app->getRequest();

        $input = '<input type="hidden" name="'.$request->csrfParam.'" value="'.$request->getCsrfToken().'">';

        return $this->asRaw($input);
    }

    /**
     * Returns the CSRF param.
     *
     * @return Response
     */
    public function actionParam(): Response
    {
        return $this->asRaw(Craft::$app->getRequest()->csrfParam);
    }

    /**
     * Returns a CSRF token.
     *
     * @return Response
     */
    public function actionToken(): Response
    {
        return $this->asRaw(Craft::$app->getRequest()->getCsrfToken());
    }

    /**
     * Returns all CSRF options in a single JSON response.
     *
     * @return Response
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
