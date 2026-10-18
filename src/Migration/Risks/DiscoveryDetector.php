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
        $listeners = $this->frameworkDiscovered($context, ClassKind::Listener, 'app/Listeners/', ['app/Providers/', 'bootstrap/']);
        $commands = $this->frameworkDiscovered($context, ClassKind::Command, 'app/Console/Commands/', ['app/Providers/', 'bootstrap/', 'app/Console/Kernel.php']);

        return array_values(array_filter([
            $this->risk(
                'Listener discovery',
                'Laravel discovers event listeners in app/Listeners. Moved listeners stop handling their events unless they are registered.',
                $this->listenerFix($context, $listeners),
                $this->itemsFor($listeners, 'app/Listeners'),
            ),
            $this->risk(
                'Command discovery',
                'Laravel loads Artisan commands from app/Console/Commands. Moved commands disappear from Artisan and the scheduler unless they are registered.',
                $this->commandFix($context, $commands),
                $this->itemsFor($commands, 'app/Console/Commands'),
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
     * @return list<ClassMove>
     */
    private function frameworkDiscovered(PlanContext $context, ClassKind $kind, string $directory, array $registrationPaths): array
    {
        return array_values(array_filter(
            $context->moves,
            fn (ClassMove $move): bool => $move->kind === $kind
                && str_starts_with($move->file, $directory)
                && ! $context->isReferencedFrom($move->class, $registrationPaths),
        ));
    }

    /**
     * @param  list<ClassMove>  $moves
     * @return list<RiskItem>
     */
    private function itemsFor(array $moves, string $directory): array
    {
        return array_map(fn (ClassMove $move): RiskItem => new RiskItem($move->class, "is discovered in {$directory}", $move->file), $moves);
    }

    /**
     * @param  list<ClassMove>  $moves
     */
    private function listenerFix(PlanContext $context, array $moves): string
    {
        $directory = $this->targetDirectory($moves);

        if (is_file($context->basePath.'/app/Providers/EventServiceProvider.php')) {
            return "Add base_path('{$directory}') to discoverEventsWithin() in app/Providers/EventServiceProvider.php, or register the listeners with Event::listen().";
        }

        return "Add ->withEvents(discover: [__DIR__.'/../app/Listeners', __DIR__.'/../{$directory}']) to bootstrap/app.php, or register the listeners with Event::listen().";
    }

    /**
     * @param  list<ClassMove>  $moves
     */
    private function commandFix(PlanContext $context, array $moves): string
    {
        $directory = $this->targetDirectory($moves);

        if (is_file($context->basePath.'/app/Console/Kernel.php')) {
            return "Add \$this->load(base_path('{$directory}')); to commands() in app/Console/Kernel.php.";
        }

        return "Add __DIR__.'/../{$directory}' to ->withCommands([...]) in bootstrap/app.php.";
    }

    /**
     * The deepest directory that contains every target file; Laravel searches it recursively.
     *
     * @param  list<ClassMove>  $moves
     */
    private function targetDirectory(array $moves): string
    {
        $common = null;

        foreach ($moves as $move) {
            $segments = explode('/', dirname($move->targetFile));
            $common ??= $segments;
            $length = 0;

            while ($length < min(count($common), count($segments)) && $common[$length] === $segments[$length]) {
                $length++;
            }

            $common = array_slice($common, 0, $length);
        }

        return implode('/', $common ?? []);
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
