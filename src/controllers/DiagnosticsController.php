<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\controllers;

use Craft;
use craft\helpers\App;
use craft\helpers\Json;
use craft\web\Controller;
use craft\web\CsvResponseFormatter;
use putyourlightson\blitz\assets\BlitzAsset;
use putyourlightson\blitz\Blitz;
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
        Sprig::bootstrap();
        Sprig::$core->components->setConfig(['requestClass' => 'busy']);

        Craft::$app->getView()->registerAssetBundle(BlitzAsset::class);

        return $this->renderTemplate('blitz/_utilities/diagnostics/' . $path, [
            'siteId' => DiagnosticsHelper::getSiteId(),
        ]);
    }

    public function actionReport(): Response
    {
        Craft::$app->getView()->registerAssetBundle(BlitzAsset::class);

        return $this->renderTemplate(
            'blitz/_utilities/diagnostics/report',
            [
                'phpVersion' => App::phpVersion(),
                'dbDriver' => $this->dbDriver(),
                'blitzPluginSettings' => $this->getRedacted(Blitz::$plugin->getSettings()->getAttributes()),
            ]
        );
    }

    public function actionExportPages(int $siteId): Response
    {
        App::maxPowerCaptain();

        $pages = DiagnosticsHelper::getPagesQuery($siteId)
            ->orderBy(['elementCount' => SORT_DESC])
            ->all();

        $values = [];
        foreach ($pages as $page) {
            $values[] = [
                'uri' => $page['uri'] ?: '/',
                'elements' => $page['elementCount'] ?: 0,
                'elementQueries' => $page['elementQueryCount'] ?: 0,
                'expiryDate' => $page['expiryDate'],
            ];
        }

        $this->response->data = $values;
        $this->response->formatters['csv'] = new CsvResponseFormatter();
        $this->response->format = 'csv';
        $this->response->setDownloadHeaders('tracked-pages.csv');

        return $this->response;
    }

    public function actionExportIncludes(int $siteId): Response
    {
        App::maxPowerCaptain();

        $includes = DiagnosticsHelper::getIncludesQuery($siteId)
            ->orderBy(['elementCount' => SORT_DESC])
            ->all();

        $values = [];
        foreach ($includes as $include) {
            $values[] = [
                'index' => $include['index'],
                'template' => $include['template'],
                'params' => $include['params'],
                'elements' => $include['elementCount'] ?: 0,
                'elementQueries' => $include['elementQueryCount'] ?: 0,
                'expiryDate' => $include['expiryDate'],
            ];
        }

        $this->response->data = $values;
        $this->response->formatters['csv'] = new CsvResponseFormatter();
        $this->response->format = 'csv';
        $this->response->setDownloadHeaders('tracked-includes.csv');

        return $this->response;
    }

    /**
     * Returns redacted values as a JSON encoded string.
     */
    private function getRedacted(array $values): string
    {
        $redacted = Craft::$app->getSecurity()->redactIfSensitive('', $values);
        $encoded = Json::encode($redacted, JSON_PRETTY_PRINT);

        // Replace unicode character with asterisk
        return str_replace('\u2022', '*', $encoded);
    }

    /**
     * Returns the DB driver name and version
     *
     * @see SystemReport::_dbDriver()
     */
    private function dbDriver(): string
    {
        $db = Craft::$app->getDb();
        $label = $db->getDriverLabel();
        $version = App::normalizeVersion($db->getSchema()->getServerVersion());
        return "$label $version";
    }
}
