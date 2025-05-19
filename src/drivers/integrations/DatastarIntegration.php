<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\integrations;

use Craft;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\enums\HeaderEnum;
use putyourlightson\blitz\events\ResponseEvent;
use putyourlightson\blitz\services\CacheRequestService;
use putyourlightson\datastar\Datastar;
use yii\base\Event;
use yii\web\Response;

/**
 * @since 5.11.0
 */
class DatastarIntegration extends BaseIntegration
{
    /**
     * @inheritdoc
     */
    public static function getRequiredModules(): array
    {
        return [
            'datastar-module',
        ];
    }

    /**
     * @inheritdoc
     */
    public static function registerEvents(): void
    {
        // Only register events if the request is a Datastar request.
        if (!self::isDatastarRequest()) {
            return;
        }

        // Set the content type header for cached responses.
        Blitz::$plugin->cacheRequest->on(CacheRequestService::EVENT_BEFORE_GET_RESPONSE,
            function(ResponseEvent $event) {
                $response = $event->response;
                $response->format = Response::FORMAT_RAW;
                $response->getHeaders()->set(HeaderEnum::CONTENT_TYPE->value, 'text/event-stream');
            },
        );

        // Save the response content after it is sent, since the content is empty for a streamed response.
        Blitz::$plugin->cacheRequest->on(CacheRequestService::EVENT_AFTER_SAVE_AND_PREPARE_RESPONSE,
            function(ResponseEvent $event) {
                $siteUri = $event->siteUri;
                Event::on(\craft\web\Response::class, Response::EVENT_AFTER_SEND,
                    function() use ($siteUri) {
                        $content = Datastar::getInstance()->sse->getResponseData();
                        Blitz::$plugin->generateCache->save($content, $siteUri);
                    },
                );
            },
        );
    }

    /**
     * Returns whether the request is a Datastar request based on the action segments rather than the header, so that Datastar request URLs can be accessed directly.
     */
    private static function isDatastarRequest(): bool
    {
        return Craft::$app->getRequest()->getIsActionRequest()
            && Craft::$app->getRequest()->getActionSegments() === ['datastar-module'];
    }
}
