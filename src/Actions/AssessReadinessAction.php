<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Actions;

use MertBag\DomainMigrator\Data\AnalysisReport;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\DomainReadiness;
use MertBag\DomainMigrator\Data\MigrationOptions;
use MertBag\DomainMigrator\Data\MigrationPlan;
use MertBag\DomainMigrator\Data\ScanResult;
use MertBag\DomainMigrator\Exceptions\InvalidMigrationPlanException;

/**
 * Plans the move of every domain in a report and ranks them from the easiest to move to the hardest.
 */
final readonly class AssessReadinessAction
{
    public function __construct(
        private PlanMigrationAction $plan,
        private BuildDomainMapAction $buildMap,
    ) {}

    /**
     * @return list<DomainReadiness>
     *
     * @throws InvalidMigrationPlanException
     */
    public function execute(AnalysisReport $report, string $basePath, MigrationOptions $migration, ?BoundaryMap $existing = null): array
    {
        $plans = $this->plan->executeAll(
            new ScanResult($report->graph, $report->skippedFiles),
            $basePath,
            $migration,
            $this->buildMap->execute($report, $existing),
        );

        return DomainReadiness::sorted(array_map(fn (MigrationPlan $plan): DomainReadiness => new DomainReadiness($plan), $plans));
    }
}
