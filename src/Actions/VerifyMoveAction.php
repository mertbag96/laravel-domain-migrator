<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Actions;

use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\ClassMove;
use MertBag\DomainMigrator\Data\MigrationOptions;
use MertBag\DomainMigrator\Data\MigrationPlan;
use MertBag\DomainMigrator\Data\VerificationResult;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;
use MertBag\DomainMigrator\Exceptions\InvalidMigrationPlanException;
use MertBag\DomainMigrator\Migration\Scanning\ProjectScanner;
use MertBag\DomainMigrator\Support\ProjectRelativePath;
use PhpParser\Error;
use PhpParser\ParserFactory;

/**
 * Checks a finished domain:move: the old files are gone, every PHP file it wrote parses, the moved classes
 * now sit in the domain namespace, nothing is left to move, and nothing still refers to an old class name.
 */
final readonly class VerifyMoveAction
{
    public function __construct(
        private ScanApplicationAction $scan,
        private PlanMigrationAction $plan,
        private ProjectScanner $projectScanner,
    ) {}

    /**
     * @param  MigrationPlan  $moved  The plan the move executed.
     * @param  list<string>  $files  The files the move wrote, relative to the base path.
     *
     * @throws InvalidConfigurationException
     * @throws InvalidMigrationPlanException
     */
    public function execute(AnalysisOptions $options, MigrationOptions $migration, BoundaryMap $map, MigrationPlan $moved, array $files): VerificationResult
    {
        $basePath = rtrim(str_replace('\\', '/', $options->basePath), '/');
        $problems = [];
        $parsed = 0;
        $parser = (new ParserFactory)->createForHostVersion();

        foreach ($moved->moves as $move) {
            if (is_file(ProjectRelativePath::toAbsolute($move->file, $basePath))) {
                $problems[] = "{$move->file} still exists after being moved to {$move->targetFile}";
            }
        }

        foreach ($files as $file) {
            $path = ProjectRelativePath::toAbsolute($file, $basePath);

            if (! is_file($path)) {
                $problems[] = "{$file} is missing";

                continue;
            }

            if (! str_ends_with($file, '.php') || str_ends_with($file, '.blade.php')) {
                continue;
            }

            try {
                $parser->parse((string) file_get_contents($path));
                $parsed++;
            } catch (Error $error) {
                $problems[] = "{$file} does not parse: {$error->getMessage()}";
            }
        }

        $plan = $this->plan->executeWithScan($this->scan->execute($options), $basePath, $migration, $map, $moved->domain);

        if ($plan->moves !== []) {
            $problems[] = sprintf(
                '%d %s of %s would still move to %s; run domain:plan %s',
                count($plan->moves),
                count($plan->moves) === 1 ? 'class' : 'classes',
                $plan->domain,
                $plan->namespace,
                $plan->domain,
            );
        }

        $inPlace = array_flip($plan->inPlace);

        foreach ($moved->moves as $move) {
            if (! isset($inPlace[$move->target])) {
                $problems[] = "{$move->target} is not part of {$plan->domain} in the domain map after the move";
            }
        }

        $oldNames = array_map(fn (ClassMove $move): string => $move->class, $moved->moves);

        foreach ($this->projectScanner->scan($basePath, $migration->referencePaths, $oldNames)->references as $reference) {
            $problems[] = "{$reference->file}:{$reference->line} still refers to {$reference->class}";
        }

        return new VerificationResult($problems, $parsed);
    }
}
