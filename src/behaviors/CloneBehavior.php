<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\behaviors;

use putyourlightson\blitz\services\GenerateCacheService;
use yii\base\Behavior;

/**
 * Used for marking an element query as a clone.
 *
 * @used-by GenerateCacheService::addRelatedElementIds()
 * @since 4.17.1
 */
class CloneBehavior extends Behavior
{
}
