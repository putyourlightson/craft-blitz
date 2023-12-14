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
