<?php

namespace putyourlightson\blitz\tests\Support;

use Craft;
use craft\elements\Entry;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\generators\BaseCacheGenerator;
use putyourlightson\blitz\models\SiteUriModel;

class DescendantUriGenerator extends BaseCacheGenerator
{
    public function generateUrisWithProgress(array $siteUris, callable $setProgressHandler = null): void
    {
        foreach ($siteUris as $siteUri) {
            $siteUri = is_array($siteUri) ? new SiteUriModel($siteUri) : $siteUri;
            $entry = Entry::find()->siteId($siteUri->siteId)->uri($siteUri->uri)->one();
            if ($entry !== null) {
                $generate = Blitz::$plugin->generateCache;
                $generate->reset();
                $generate->addElement($entry);
                $output = Craft::$app->getView()->renderTemplate($entry->getSection()->getSiteSettings()[$entry->siteId]->template, [
                    'entry' => $entry,
                ]);
                $generate->save($output, $siteUri);
            }
        }
    }
}
