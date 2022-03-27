<?php

declare(strict_types=1);

use craft\ecs\SetList;
use PhpCsFixer\Fixer\ControlStructure\ControlStructureContinuationPositionFixer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;

return static function(ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();
    $parameters->set(Option::PARALLEL, true);
    $parameters->set(Option::PATHS, [
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __FILE__,
    ]);

    $containerConfigurator->import(SetList::CRAFT_CMS_4);

    // Sets the control structure continuation keyword to be on the next line.
    $services = $containerConfigurator->services();
    $services->set(ControlStructureContinuationPositionFixer::class)
        ->call('configure', [[
            'position' => ControlStructureContinuationPositionFixer::NEXT_LINE,
        ]]);
};
