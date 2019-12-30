<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit;

use Codeception\Test\Unit;
use Craft;
use craft\elements\Entry;
use crafttests\fixtures\EntryFixture;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\models\SiteUriModel;
use UnitTester;

/**
 * @author    PutYourLightsOn
 * @package   Blitz
 * @since     3.1.0
 */

class RefreshCacheTest extends Unit
{
    // Properties
    // =========================================================================

    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var Entry
     */
    private $entry;

    /**
     * @var SiteUriModel
     */
    private $siteUri;

    /**
     * @var string
     */
    private $output = 'xyz';

    // Fixtures
    // =========================================================================

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'entries' => [
                'class' => EntryFixture::class
            ],
        ];
    }

    // Protected methods
    // =========================================================================

    protected function _before()
    {
        parent::_before();

        Blitz::$plugin->generateCache->options->cachingEnabled = true;

        Blitz::$plugin->cacheStorage->deleteAll();
        Blitz::$plugin->flushCache->flushAll();

        $this->entry = Entry::find()->sectionId(1003)->one();

        $this->siteUri = new SiteUriModel([
            'siteId' => 1,
            'uri' => 'page',
        ]);
    }

    // Public methods
    // =========================================================================

    public function testCacheRefreshed()
    {
        // Add element query and save
        $elementQuery = Entry::find()->sectionId($this->entry->sectionId);
        Blitz::$plugin->generateCache->addElementQuery($elementQuery);
        Blitz::$plugin->generateCache->save($this->output, $this->siteUri);

        $value = Blitz::$plugin->cacheStorage->get($this->siteUri);

        // Assert that the output (which may also contain a timestamp) contains the cached value
        $this->assertStringContainsString($this->output, $value);

        // Add the entry and refresh
        Blitz::$plugin->refreshCache->addElement($this->entry);
        Blitz::$plugin->refreshCache->refresh(true);

        // Run the queue
        Craft::$app->getQueue()->run();

        $value = Blitz::$plugin->cacheStorage->get($this->siteUri);

        // Assert that the cached value is a blank string
        $this->assertEquals('', $value);
    }
}
