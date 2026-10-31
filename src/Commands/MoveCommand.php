<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Commands;

use Illuminate\Console\Command;
use MertBag\DomainMigrator\Actions\MoveDomainAction;
use MertBag\DomainMigrator\Actions\VerifyMoveAction;
use MertBag\DomainMigrator\Boundaries\DomainMapReader;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\BoundaryOptions;
use MertBag\DomainMigrator\Data\MigrationOptions;
use MertBag\DomainMigrator\Data\MoveOptions;
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
        {--force : Move even when the plan has breaking risks (blockers always stop the move)}
        {--no-backup : Skip writing a rollback backup (not recommended)}
        {--verify : After the move, check that every written file parses and that planning the domain again finds nothing left to move}';

    /** @var string */
    protected $description = 'Move the classes of a domain into the domain namespace and update every reference to them';

    public function handle(MoveDomainAction $move, VerifyMoveAction $verify, DomainMapReader $mapReader): int
    {
        try {
            $mapPath = $this->stringOption('map') ?? $this->laravel->make(BoundaryOptions::class)->mapPath;

            $result = $move->execute(
                $this->laravel->make(AnalysisOptions::class),
                $this->laravel->make(MigrationOptions::class),
                $mapReader->read($mapPath),
                $this->stringArgument('domain'),
                $this->laravel->make(MoveOptions::class),
                $mapPath,
                (bool) $this->option('dry-run'),
                (bool) $this->option('force'),
                (bool) $this->option('no-backup'),
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

        if ($result->backupRelativePath !== null) {
            $this->newLine();
            $this->line("  Backup: {$result->backupRelativePath}");
            $this->line('  Rollback: php artisan domain:rollback '.$result->backupId);
        }

        $this->newLine();
        $verifying = (bool) $this->option('verify') && ! $result->dryRun;

        if (! $verifying) {
            $this->components->info($result->dryRun ? 'Dry run: nothing was changed.' : 'Done. Run composer dump-autoload, then your tests.');
        }

        if ($verifying) {
            return $this->verify($verify, $mapReader, $mapPath, $result->plan->domain, [...array_values($result->moved), ...$result->updated], $result->backupId);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $files
     */
    private function verify(VerifyMoveAction $verify, DomainMapReader $mapReader, string $mapPath, string $domain, array $files, ?string $backupId): int
    {
        try {
            $verification = $verify->execute(
                $this->laravel->make(AnalysisOptions::class),
                $this->laravel->make(MigrationOptions::class),
                $mapReader->read($mapPath),
                $domain,
                $files,
            );
        } catch (InvalidConfigurationException|InvalidDomainMapException|InvalidMigrationPlanException $exception) {
            $this->components->error("Could not verify the move: {$exception->getMessage()}");

            return self::FAILURE;
        }

        if ($verification->passed()) {
            $this->components->info(sprintf('Verified: %d %s parse and nothing is left to move. Run composer dump-autoload, then your tests.', count($files), count($files) === 1 ? 'file' : 'files'));

            return self::SUCCESS;
        }

        $this->components->warn('Verification found problems:');

        foreach ($verification->problems as $problem) {
            $this->line("  {$problem}");
        }

        $this->line($backupId === null
            ? '  The move was kept. Fix these by hand.'
            : "  The move was kept. Fix these by hand, or restore the files with: php artisan domain:rollback {$backupId}");

        return self::FAILURE;
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
