<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use craft\web\CsvResponseFormatter;
use putyourlightson\blitz\assets\BlitzAsset;
use putyourlightson\blitz\helpers\DiagnosticsHelper;
use putyourlightson\sprig\Sprig;
use yii\web\Response;

/**
 * @since 4.10.0
 */
class DiagnosticsController extends Controller
{
    public function beforeAction($action): bool
    {
        $this->requirePermission('utility:blitz-diagnostics');

        return parent::beforeAction($action);
    }

    public function actionIndex(string $path): Response
    {
        Craft::$app->getView()->registerAssetBundle(BlitzAsset::class);

        Sprig::bootstrap();
        Sprig::$core->components->setConfig(['requestClass' => 'busy']);

        $siteId = null;
        $site = Craft::$app->getRequest()->getParam('site');
        if ($site) {
            $site = Craft::$app->getSites()->getSiteByHandle($site);
            $siteId = $site ? $site->id : null;
        }
        if (empty($siteId)) {
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        }

        return $this->renderTemplate('blitz/_utilities/diagnostics/' . $path, [
            'siteId' => $siteId,
        ]);
    }

    public function actionExportPages(int $siteId): Response
    {
        App::maxPowerCaptain();

        $pages = DiagnosticsHelper::getPagesQuery($siteId)
            ->orderBy(['elementCount' => SORT_DESC])
            ->all();

        $values = [];
        foreach ($pages as &$page) {
            $values[] = [
                'uri' => $page['uri'] ?: '/',
                'elements' => $page['elementCount'] ?: 0,
                'elementQueries' => $page['elementQueryCount'] ?: 0,
            ];
        }

        $this->response->data = $values;
        $this->response->formatters['csv'] = new CsvResponseFormatter();
        $this->response->format = 'csv';
        $this->response->setDownloadHeaders('tracked-pages.csv');

        return $this->response;
    }
}
