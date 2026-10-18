<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Risks;

use MertBag\DomainMigrator\Data\ClassMove;
use MertBag\DomainMigrator\Data\MigrationRisk;
use MertBag\DomainMigrator\Data\PlanContext;
use MertBag\DomainMigrator\Data\RiskItem;
use MertBag\DomainMigrator\Enums\ClassKind;
use MertBag\DomainMigrator\Enums\RiskLevel;
use MertBag\DomainMigrator\Enums\RiskType;

/**
 * Classes that Laravel or a package finds by directory or namespace rather than by registration.
 */
final readonly class DiscoveryDetector implements RiskDetector
{
    /** Package base classes that are discovered by directory or namespace. */
    private const array DISCOVERED_BASES = [
        'Livewire\\Component' => 'is found by Livewire through livewire.class_namespace',
        'Filament\\Resources\\Resource' => 'is found by Filament through discoverResources()',
        'Filament\\Pages\\Page' => 'is found by Filament through discoverPages()',
        'Filament\\Widgets\\Widget' => 'is found by Filament through discoverWidgets()',
        'Laravel\\Nova\\Resource' => 'is registered by Nova from app/Nova',
    ];

    public function detect(PlanContext $context): array
    {
        return array_values(array_filter([
            $this->risk(
                'Listener discovery',
                'Laravel discovers event listeners in app/Listeners. Moved listeners stop handling their events unless they are registered.',
                'Add the new directory to ->withEvents(discover: [...]) in bootstrap/app.php, or register the listeners with Event::listen().',
                $this->frameworkDiscovered($context, ClassKind::Listener, 'app/Listeners/', ['app/Providers/', 'bootstrap/']),
            ),
            $this->risk(
                'Command discovery',
                'Laravel loads Artisan commands from app/Console/Commands. Moved commands disappear from Artisan and the scheduler unless they are registered.',
                'Add the new directory to ->withCommands([...]) in bootstrap/app.php.',
                $this->frameworkDiscovered($context, ClassKind::Command, 'app/Console/Commands/', ['app/Providers/', 'bootstrap/', 'app/Console/Kernel.php']),
            ),
            $this->risk(
                'Package discovery',
                'Livewire, Filament and Nova find these classes by namespace or directory. Moved classes are no longer found.',
                'Register them explicitly (Livewire::component(), ->resources([...]) on the panel, Nova::resources()), or point the package at the new namespace or directory.',
                $this->packageDiscovered($context),
            ),
        ]));
    }

    /**
     * @param  list<string>  $registrationPaths
     * @return list<RiskItem>
     */
    private function frameworkDiscovered(PlanContext $context, ClassKind $kind, string $directory, array $registrationPaths): array
    {
        $moves = array_filter(
            $context->moves,
            fn (ClassMove $move): bool => $move->kind === $kind
                && str_starts_with($move->file, $directory)
                && ! $context->isReferencedFrom($move->class, $registrationPaths),
        );

        return array_values(array_map(
            fn (ClassMove $move): RiskItem => new RiskItem($move->class, 'is discovered in '.rtrim($directory, '/'), $move->file),
            $moves,
        ));
    }

    /**
     * @return list<RiskItem>
     */
    private function packageDiscovered(PlanContext $context): array
    {
        $items = [];

        foreach ($context->moves as $move) {
            foreach ($context->project->ancestors($move->class) as $ancestor) {
                if (isset(self::DISCOVERED_BASES[$ancestor])) {
                    $items[] = new RiskItem($move->class, self::DISCOVERED_BASES[$ancestor], $move->file);

                    break;
                }
            }
        }

        return $items;
    }

    /**
     * @param  list<RiskItem>  $items
     */
    private function risk(string $title, string $message, string $fix, array $items): ?MigrationRisk
    {
        return $items === [] ? null : new MigrationRisk(RiskType::Discovery, RiskLevel::Breaking, $title, $message, $fix, $items);
    }
}
