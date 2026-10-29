<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Commands;

use Illuminate\Console\Command;
use MertBag\DomainMigrator\Actions\RollbackDomainAction;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\MoveOptions;
use MertBag\DomainMigrator\Exceptions\MoveRollbackException;
use MertBag\DomainMigrator\Migration\Move\MoveSnapshotStore;

final class RollbackCommand extends Command
{
    /** @var string */
    protected $signature = 'domain:rollback
        {id? : The backup id from domain:move (default: latest backup)}
        {--list : List available move backups}
        {--dry-run : Show what would be restored without writing anything}';

    /** @var string */
    protected $description = 'Restore the project files from a domain:move backup';

    public function handle(
        RollbackDomainAction $rollback,
        MoveSnapshotStore $snapshots,
    ): int {
        $options = $this->laravel->make(MoveOptions::class);
        $basePath = $this->laravel->make(AnalysisOptions::class)->basePath;

        try {
            if ((bool) $this->option('list')) {
                return $this->listBackups($snapshots, $basePath, $options);
            }

            $id = $this->stringArgument('id');
            $manifest = $id === null || $id === ''
                ? $snapshots->latest($basePath, $options)
                : $snapshots->read($basePath, $options, $id);

            $result = $rollback->execute($basePath, $options, $manifest, (bool) $this->option('dry-run'));
        } catch (MoveRollbackException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        $this->line(sprintf(
            '  %s backup [%s] for domain [%s]',
            $result->dryRun ? 'Would restore' : 'Restored',
            $result->manifest->id,
            $result->manifest->domain,
        ));
        $this->newLine();

        foreach ($result->manifest->moved as $from => $to) {
            $this->line("  {$to} → {$from}");
        }

        if ($result->manifest->updated !== []) {
            $this->newLine();
            $this->line(sprintf('  %s %d updated %s:', $result->dryRun ? 'Would restore' : 'Restored', count($result->manifest->updated), count($result->manifest->updated) === 1 ? 'file' : 'files'));

            foreach ($result->manifest->updated as $file) {
                $this->line("  {$file}");
            }
        }

        $this->newLine();
        $this->components->info($result->dryRun
            ? 'Dry run: nothing was changed.'
            : 'Rollback complete. Run composer dump-autoload, then your tests.');

        return self::SUCCESS;
    }

    private function listBackups(MoveSnapshotStore $snapshots, string $basePath, MoveOptions $options): int
    {
        $manifests = $snapshots->list($basePath, $options);

        if ($manifests === []) {
            $this->components->warn('No move backups found.');

            return self::SUCCESS;
        }

        foreach ($manifests as $manifest) {
            $this->line(sprintf(
                '  %s  %s  (%d moved, %d updated)',
                $manifest->id,
                $manifest->domain,
                count($manifest->moved),
                count($manifest->updated),
            ));
        }

        return self::SUCCESS;
    }

    private function stringArgument(string $name): ?string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : null;
    }
}
