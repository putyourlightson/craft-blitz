<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitztests\unit\controllers;

use Craft;
use craft\web\Response;
use yii\web\BadRequestHttpException;

/**
 * @since 2.3.0
 */

class TemplatesControllerTest extends BaseControllerTest
{
    public function testGetSuccess()
    {
        Craft::$app->getView()->setTemplateMode('site');

        $response = $this->runActionWithParams('templates/get', [
            'template' => Craft::$app->getSecurity()->hashData('_hidden'),
            'params' => [
                'number' => 123,
            ],
        ]);

        $this->assertInstanceOf(Response::class, $response);

        // Assert that the output is correct
        $this->assertEquals('xyz123', trim($response->data));
    }

    public function testGetBadRequestHttpException()
    {
        // Expect an exception
        $this->expectException(BadRequestHttpException::class);

        $this->runActionWithParams('templates/get', [
            'template' => '_nonexistant',
        ]);
    }
}
