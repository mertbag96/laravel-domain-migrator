<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Commands;

use Illuminate\Console\Command;
use MertBag\DomainMigrator\Actions\MoveDomainAction;
use MertBag\DomainMigrator\Boundaries\DomainMapReader;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\BoundaryOptions;
use MertBag\DomainMigrator\Data\MigrationOptions;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;
use MertBag\DomainMigrator\Exceptions\InvalidDomainMapException;
use MertBag\DomainMigrator\Exceptions\InvalidMigrationPlanException;

final class MoveCommand extends Command
{
    /** @var string */
    protected $signature = 'domain:move
        {domain : The domain from the domain map to move}
        {--map= : The domain map (default: boundaries.map, domain-map.json)}
        {--dry-run : Show what would be moved and updated without writing anything}
        {--force : Move even when the plan has breaking risks (blockers always stop the move)}';

    /** @var string */
    protected $description = 'Move the classes of a domain into the domain namespace and update every reference to them';

    public function handle(MoveDomainAction $move, DomainMapReader $mapReader): int
    {
        try {
            $mapPath = $this->stringOption('map') ?? $this->laravel->make(BoundaryOptions::class)->mapPath;

            $result = $move->execute(
                $this->laravel->make(AnalysisOptions::class),
                $this->laravel->make(MigrationOptions::class),
                $mapReader->read($mapPath),
                $this->stringArgument('domain'),
                $mapPath,
                (bool) $this->option('dry-run'),
                (bool) $this->option('force'),
            );
        } catch (InvalidConfigurationException|InvalidDomainMapException|InvalidMigrationPlanException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        $this->line(sprintf(
            '  %s → %s (%d %s)',
            $result->plan->domain,
            $result->plan->namespace,
            count($result->moved),
            $result->dryRun ? 'classes would move' : 'classes moved',
        ));
        $this->newLine();

        foreach ($result->moved as $from => $to) {
            $this->line("  {$from} → {$to}");
        }

        if ($result->updated !== []) {
            $this->newLine();
            $this->line(sprintf('  %s %d other %s:', $result->dryRun ? 'Would update' : 'Updated', count($result->updated), count($result->updated) === 1 ? 'file' : 'files'));

            foreach ($result->updated as $file) {
                $this->line("  {$file}");
            }
        }

        if ($result->manual !== []) {
            $this->newLine();
            $this->components->warn('Needs a look by hand:');

            foreach ($result->manual as $item) {
                $this->line("  {$item}");
            }
        }

        $this->newLine();
        $this->components->info($result->dryRun ? 'Dry run: nothing was changed.' : 'Done. Run composer dump-autoload, then your tests.');

        return self::SUCCESS;
    }

    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
