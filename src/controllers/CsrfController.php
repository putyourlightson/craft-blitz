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
    protected $allowAnonymous = ['input'];

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
}
