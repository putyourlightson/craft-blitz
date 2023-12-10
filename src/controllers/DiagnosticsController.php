<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use craft\helpers\App;
use craft\web\Controller;
use craft\web\CsvResponseFormatter;
use craft\web\Response;
use putyourlightson\blitz\helpers\DiagnosticsHelper;

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

    public function actionExport(int $siteId): Response
    {
        App::maxPowerCaptain();

        $pages = DiagnosticsHelper::getPagesQuery($siteId)
            ->orderBy(['elementCount' => SORT_DESC])
            ->asArray()
            ->all();

        // Replaces an empty URI with a slash.
        array_walk($pages, fn(&$page) => $page['uri'] = $page['uri'] ?: '/');

        $this->response->data = $pages;
        $this->response->formatters['csv'] = new CsvResponseFormatter();
        $this->response->format = 'csv';
        $this->response->setDownloadHeaders('tracked-pages.csv');

        return $this->response;
    }
}
