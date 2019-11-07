<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\helpers\StringHelper;
use craft\web\Controller;
use craft\web\View;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use yii\web\Response;

class WarmerController extends Controller
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
     * Warms a site URI.
     *
     * @return Response
     */
    public function actionWarmSiteUri(): Response
    {
        $request = Craft::$app->getRequest();

        $siteId = $request->getRequiredParam('siteId');
        $uri = $request->getRequiredParam('uri');

        $siteUri = new SiteUriModel([
            'siteId' => $siteId,
            'uri' => $uri,
        ]);

        Blitz::$plugin->cacheWarmer->warmUri($siteUri);

        return $this->asRaw(true);
    }
}
