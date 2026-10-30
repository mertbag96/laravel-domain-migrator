<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Actions;

use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\MigrationOptions;
use MertBag\DomainMigrator\Data\VerificationResult;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;
use MertBag\DomainMigrator\Exceptions\InvalidMigrationPlanException;
use PhpParser\Error;
use PhpParser\ParserFactory;

/**
 * Checks a finished domain:move: every file it wrote still parses, and planning the domain again
 * finds nothing left to move.
 */
final readonly class VerifyMoveAction
{
    public function __construct(
        private ScanApplicationAction $scan,
        private PlanMigrationAction $plan,
    ) {}

    /**
     * @param  list<string>  $files  The files the move wrote, relative to the base path.
     *
     * @throws InvalidConfigurationException
     * @throws InvalidMigrationPlanException
     */
    public function execute(AnalysisOptions $options, MigrationOptions $migration, BoundaryMap $map, string $domain, array $files): VerificationResult
    {
        $basePath = rtrim(str_replace('\\', '/', $options->basePath), '/');
        $problems = [];
        $parser = (new ParserFactory)->createForHostVersion();

        foreach ($files as $file) {
            $path = preg_match('#^(/|[A-Za-z]:[\\\\/])#', $file) === 1 ? $file : $basePath.'/'.$file;

            if (! is_file($path)) {
                $problems[] = "{$file} is missing";

                continue;
            }

            if (! str_ends_with($file, '.php') || str_ends_with($file, '.blade.php')) {
                continue;
            }

            try {
                $parser->parse((string) file_get_contents($path));
            } catch (Error $error) {
                $problems[] = "{$file} does not parse: {$error->getMessage()}";
            }
        }

        $plan = $this->plan->executeWithScan($this->scan->execute($options), $basePath, $migration, $map, $domain);

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

        return new VerificationResult($problems);
    }
}
