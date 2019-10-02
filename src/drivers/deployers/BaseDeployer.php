<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\drivers\deployers;

use craft\base\SavableComponent;

abstract class BaseDeployer extends SavableComponent implements DeployerInterface
{
    // Traits
    // =========================================================================

    use DeployerTrait;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function deploy()
    {
    }
}
