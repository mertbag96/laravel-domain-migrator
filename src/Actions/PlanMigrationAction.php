<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Actions;

use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\ClassReference;
use MertBag\DomainMigrator\Data\DomainDefinition;
use MertBag\DomainMigrator\Data\MigrationOptions;
use MertBag\DomainMigrator\Data\MigrationPlan;
use MertBag\DomainMigrator\Data\MigrationRisk;
use MertBag\DomainMigrator\Data\PlanContext;
use MertBag\DomainMigrator\Data\SkippedFile;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;
use MertBag\DomainMigrator\Exceptions\InvalidMigrationPlanException;
use MertBag\DomainMigrator\Migration\Psr4Reader;
use MertBag\DomainMigrator\Migration\Risks\RiskDetector;
use MertBag\DomainMigrator\Migration\Scanning\ProjectScanner;
use MertBag\DomainMigrator\Migration\TargetResolver;

/**
 * Works out what moving one domain into its own namespace would change and what it could break.
 * Read-only: nothing is written.
 */
final readonly class PlanMigrationAction
{
    /** @var list<RiskDetector> */
    private array $detectors;

    public function __construct(
        private ScanApplicationAction $scan,
        private Psr4Reader $psr4,
        private TargetResolver $targets,
        private ProjectScanner $projectScanner,
        RiskDetector ...$detectors,
    ) {
        $this->detectors = array_values($detectors);
    }

    /**
     * @throws InvalidConfigurationException
     * @throws InvalidMigrationPlanException
     */
    public function execute(AnalysisOptions $options, MigrationOptions $migration, BoundaryMap $map, string $domain): MigrationPlan
    {
        $definition = $this->domain($map, $domain);
        $scan = $this->scan->execute($options);
        $roots = $this->psr4->read($options->basePath);
        $namespace = $this->targets->namespaceOf($definition->name, $migration);

        $moves = [];
        $inPlace = [];
        $unresolved = [];

        foreach ($scan->graph->nodes as $node) {
            if ($map->domainOf($node->name) !== $definition->name) {
                continue;
            }

            if ($this->targets->isInPlace($node->name, $namespace)) {
                $inPlace[] = $node->name;

                continue;
            }

            $move = $this->targets->resolve($node, $definition->name, $namespace, $roots);

            if ($move === null) {
                $unresolved[] = $node->name;
            } else {
                $moves[$node->name] = $move;
            }
        }

        $project = $this->projectScanner->scan($options->basePath, $migration->referencePaths, array_keys($moves));
        $context = new PlanContext($definition->name, $options->basePath, $moves, $unresolved, $roots, $scan->graph, $project);

        return new MigrationPlan(
            domain: $definition->name,
            namespace: $namespace,
            moves: array_values($moves),
            inPlace: $inPlace,
            references: $this->sortedReferences($project->references),
            risks: $this->risks($context),
            skippedFiles: $this->skippedFiles([...$scan->skippedFiles, ...$project->skippedFiles]),
        );
    }

    private function domain(BoundaryMap $map, string $name): DomainDefinition
    {
        $definition = $map->domain($name);

        if ($definition !== null) {
            return $definition;
        }

        foreach ($map->domains as $candidate) {
            if (strcasecmp($candidate->name, $name) === 0) {
                return $candidate;
            }
        }

        throw InvalidMigrationPlanException::unknownDomain(
            $name,
            array_map(fn (DomainDefinition $domain): string => $domain->name, $map->domains),
        );
    }

    /**
     * @return list<MigrationRisk>
     */
    private function risks(PlanContext $context): array
    {
        $risks = array_merge([], ...array_map(fn (RiskDetector $detector): array => $detector->detect($context), $this->detectors));

        usort($risks, fn (MigrationRisk $a, MigrationRisk $b): int => $a->level->order() <=> $b->level->order());

        return $risks;
    }

    /**
     * @param  list<ClassReference>  $references
     * @return list<ClassReference>
     */
    private function sortedReferences(array $references): array
    {
        usort($references, fn (ClassReference $a, ClassReference $b): int => [$a->file, $a->line, $a->class] <=> [$b->file, $b->line, $b->class]);

        return $references;
    }

    /**
     * @param  list<SkippedFile>  $files
     * @return list<SkippedFile>
     */
    private function skippedFiles(array $files): array
    {
        $unique = [];

        foreach ($files as $file) {
            $unique[$file->file] ??= $file;
        }

        ksort($unique);

        return array_values($unique);
    }
}
