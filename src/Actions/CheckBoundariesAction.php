<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Actions;

use MertBag\DomainMigrator\Boundaries\BoundaryChecker;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\Baseline;
use MertBag\DomainMigrator\Data\BoundaryCheckResult;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\DomainDefinition;
use MertBag\DomainMigrator\Data\Violation;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;

final readonly class CheckBoundariesAction
{
    public function __construct(
        private ScanApplicationAction $scan,
        private BoundaryChecker $checker,
    ) {}

    /**
     * @throws InvalidConfigurationException
     */
    public function execute(AnalysisOptions $options, BoundaryMap $map, Baseline $baseline = new Baseline): BoundaryCheckResult
    {
        $scan = $this->scan->execute($options);
        $violations = $this->checker->check($scan->graph, $map);
        [$baselined, $new] = $this->partition($violations, $baseline);

        return new BoundaryCheckResult(
            violations: $new,
            baselined: $baselined,
            fixedEntries: $baseline->fixedEntries($violations),
            uncheckedDomains: array_values(array_map(
                fn (DomainDefinition $domain): string => $domain->name,
                array_filter($map->domains, fn (DomainDefinition $domain): bool => ! $domain->isChecked()),
            )),
            unmappedClasses: array_values(array_filter(array_keys($scan->graph->nodes), fn (string $class): bool => ! $map->isMapped($class))),
            skippedFiles: $scan->skippedFiles,
            classCount: count($scan->graph->nodes),
        );
    }

    /**
     * @param  list<Violation>  $violations
     * @return array{list<Violation>, list<Violation>} Baselined violations, then new ones.
     */
    private function partition(array $violations, Baseline $baseline): array
    {
        $baselined = [];
        $new = [];

        foreach ($violations as $violation) {
            if ($baseline->contains($violation)) {
                $baselined[] = $violation;
            } else {
                $new[] = $violation;
            }
        }

        return [$baselined, $new];
    }
}
