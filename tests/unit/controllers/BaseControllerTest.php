<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit\controllers;

use Codeception\Test\Unit;
use Craft;
use putyourlightson\blitz\Blitz;

/**
 * @since 2.3.0
 */
class BaseControllerTest extends Unit
{
    protected function _before()
    {
        parent::_before();

        // Set controller namespace to web
        Blitz::$plugin->controllerNamespace = str_replace('\\console', '', Blitz::$plugin->controllerNamespace);
    }

    protected function runActionWithParams(string $action, array $params): mixed
    {
        Craft::$app->request->setBodyParams($params);

        return Blitz::$plugin->runAction($action);
    }
}
