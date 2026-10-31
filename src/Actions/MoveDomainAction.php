<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Actions;

use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\ClassMove;
use MertBag\DomainMigrator\Data\MigrationOptions;
use MertBag\DomainMigrator\Data\MoveOptions;
use MertBag\DomainMigrator\Data\MoveResult;
use MertBag\DomainMigrator\Enums\RiskLevel;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;
use MertBag\DomainMigrator\Exceptions\InvalidMigrationPlanException;
use MertBag\DomainMigrator\Migration\Move\MoveSnapshotStore;
use MertBag\DomainMigrator\Migration\Rewriting\PhpRewriter;
use MertBag\DomainMigrator\Migration\Scanning\ClassNameMatcher;
use MertBag\DomainMigrator\Support\ProjectRelativePath;
use PhpParser\Error;
use RuntimeException;
use Throwable;

/**
 * Moves the classes of one domain into the domain namespace: the files are moved, their namespaces
 * are rewritten, and every reference to them is updated.
 */
final readonly class MoveDomainAction
{
    public function __construct(
        private ScanApplicationAction $scan,
        private PlanMigrationAction $plan,
        private PhpRewriter $rewriter,
        private MoveSnapshotStore $snapshots,
        private RollbackDomainAction $rollback,
    ) {}

    /**
     * @param  ?string  $mapPath  The domain map file, whose class names are updated too.
     *
     * @throws InvalidConfigurationException
     * @throws InvalidMigrationPlanException
     */
    public function execute(
        AnalysisOptions $options,
        MigrationOptions $migration,
        BoundaryMap $map,
        string $domain,
        MoveOptions $moveOptions,
        ?string $mapPath = null,
        bool $dryRun = false,
        bool $force = false,
        bool $withoutBackup = false,
    ): MoveResult {
        $scan = $this->scan->execute($options);
        $plan = $this->plan->executeWithScan($scan, $options->basePath, $migration, $map, $domain);

        if ($plan->count(RiskLevel::Blocker) > 0) {
            throw InvalidMigrationPlanException::blocked($plan->domain, $plan->count(RiskLevel::Blocker));
        }

        if (! $force && $plan->count(RiskLevel::Breaking) > 0) {
            throw InvalidMigrationPlanException::breaking($plan->domain, $plan->count(RiskLevel::Breaking));
        }

        $basePath = rtrim(str_replace('\\', '/', realpath($options->basePath) ?: $options->basePath), '/');
        $classes = [];
        $byFile = [];

        foreach ($plan->moves as $move) {
            $classes[$move->class] = $move->target;
            $byFile[$move->file] ??= $move;

            if (is_file($basePath.'/'.$move->targetFile)) {
                throw InvalidMigrationPlanException::targetExists($move->targetFile);
            }
        }

        $known = array_fill_keys(array_keys($scan->graph->nodes), true);
        $files = array_unique([...array_keys($byFile), ...array_map(fn ($reference): string => $reference->file, $plan->references)]);
        sort($files);

        $originals = $this->originals($basePath, $files, $mapPath);

        $contents = [];
        $manual = [];

        foreach ($files as $file) {
            $rewritten = $this->rewrite($basePath, $file, $classes, $known, $byFile[$file] ?? null);

            if ($rewritten !== null) {
                $contents[$file] = $rewritten[0];
                array_push($manual, ...$rewritten[1]);
            }
        }

        if ($mapPath !== null && is_file($mapPath)) {
            $mapContents = (string) file_get_contents($mapPath);
            $contents[$mapPath] = ClassNameMatcher::replace($mapContents, $classes);
        }

        $moved = array_map(fn (ClassMove $move): string => $move->targetFile, $byFile);
        $updated = array_keys(array_filter(
            $contents,
            fn (string $code, string $file): bool => ! isset($moved[$file]) && $code !== (string) file_get_contents($this->absolute($basePath, $file)),
            ARRAY_FILTER_USE_BOTH,
        ));

        $backupId = null;
        $backupRelativePath = null;
        $shouldBackup = ! $dryRun && $moveOptions->backup && ! $withoutBackup;
        $backupManifest = null;

        if ($shouldBackup) {
            $backupManifest = $this->snapshots->write(
                $basePath,
                $moveOptions,
                $plan->domain,
                $mapPath,
                $moved,
                $this->relativePaths($basePath, $updated),
                $originals,
            );
            $backupId = $backupManifest->id;
            $backupRelativePath = $moveOptions->backupPath.'/'.$backupManifest->id;
        }

        if (! $dryRun) {
            try {
                $this->write($basePath, $moved, $contents, $updated);
            } catch (Throwable $exception) {
                if ($backupManifest !== null) {
                    $this->rollback->execute($basePath, $moveOptions, $backupManifest);
                }

                throw $exception;
            }
        }

        return new MoveResult($plan, $moved, $this->relativePaths($basePath, $updated), array_values(array_unique($manual)), $dryRun, $backupId, $backupRelativePath);
    }

    /**
     * @param  list<string>  $files
     * @return array<string, string>
     */
    private function originals(string $basePath, array $files, ?string $mapPath): array
    {
        $originals = [];

        foreach ($files as $file) {
            $path = $this->absolute($basePath, $file);

            if (is_file($path)) {
                $originals[ProjectRelativePath::toRelative($path, $basePath)] = (string) file_get_contents($path);
            }
        }

        if ($mapPath !== null && is_file($mapPath)) {
            $relative = ProjectRelativePath::toRelative($mapPath, $basePath);
            $originals[$relative] = (string) file_get_contents($mapPath);
        }

        return $originals;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function relativePaths(string $basePath, array $paths): array
    {
        return array_map(
            fn (string $path): string => ProjectRelativePath::toRelative($this->absolute($basePath, $path), $basePath),
            $paths,
        );
    }

    /**
     * @param  array<string, string>  $classes
     * @param  array<string, true>  $known
     * @return ?array{string, list<string>} The new contents and what needs a look by hand; null for a missing file.
     *
     * @throws InvalidMigrationPlanException
     */
    private function rewrite(string $basePath, string $file, array $classes, array $known, ?ClassMove $move): ?array
    {
        $path = $this->absolute($basePath, $file);

        if (! is_file($path)) {
            return null;
        }

        $code = (string) file_get_contents($path);

        if (str_ends_with($file, '.php') && ! str_ends_with($file, '.blade.php')) {
            try {
                $newNamespace = $move === null ? null : substr($move->target, 0, (int) strrpos($move->target, '\\'));
                $rewritten = $this->rewriter->rewrite($code, $file, $classes, $known, $newNamespace);

                return [$rewritten->code, $rewritten->manual];
            } catch (Error $error) {
                if ($move !== null) {
                    throw InvalidMigrationPlanException::unparsable($file, $error->getMessage());
                }

                return [$code, ["{$file} could not be parsed and was not updated"]];
            }
        }

        return [ClassNameMatcher::replace($code, $classes), []];
    }

    /**
     * @param  array<string, string>  $moved
     * @param  array<string, string>  $contents
     * @param  list<string>  $updated
     */
    private function write(string $basePath, array $moved, array $contents, array $updated): void
    {
        foreach ($moved as $from => $to) {
            $target = $this->absolute($basePath, $to);

            if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0777, true) && ! is_dir(dirname($target))) {
                throw new RuntimeException("Could not create directory for [{$to}].");
            }

            file_put_contents($target, $contents[$from]);
            unlink($this->absolute($basePath, $from));
        }

        foreach ($updated as $file) {
            file_put_contents($this->absolute($basePath, $file), $contents[$file]);
        }
    }

    private function absolute(string $basePath, string $file): string
    {
        return ProjectRelativePath::toAbsolute($file, $basePath);
    }
}
