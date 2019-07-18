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
    protected $allowAnonymous = ['input', 'param', 'token'];

    // Public Methods
    // =========================================================================

    /**
     * Returns a CSRF input field.
     *
     * @return Response
     */
    public function actionInput(): Response
    {
        $generalConfig = Craft::$app->getConfig()->getGeneral();

        if (!$generalConfig->enableCsrfProtection) {
            return $this->asRaw('');
        }

        $input = '<input type="hidden" name="'.$generalConfig->csrfTokenName.'" value="'.Craft::$app->getRequest()->getCsrfToken().'">';

        return $this->asRaw($input);
    }

    public function actionParam(): Response
    {
        $generalConfig = Craft::$app->getConfig()->getGeneral();

        if (!$generalConfig->enableCsrfProtection) {
            return $this->asRaw('');
        }

        $param = $generalConfig->csrfTokenName;

        return $this->asRaw($param);
    }

    public function actionToken(): Response
    {
        $generalConfig = Craft::$app->getConfig()->getGeneral();

        if (!$generalConfig->enableCsrfProtection) {
            return $this->asRaw('');
        }

        $token = Craft::$app->getRequest()->getCsrfToken();

        return $this->asRaw($token);
    }
}
