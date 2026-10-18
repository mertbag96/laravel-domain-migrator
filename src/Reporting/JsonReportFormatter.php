<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Reporting;

use MertBag\DomainMigrator\Data\AnalysisReport;
use MertBag\DomainMigrator\Data\BoundaryCheckResult;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\MigrationPlan;

final readonly class JsonReportFormatter
{
    private const int FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    public function format(AnalysisReport $report): string
    {
        return json_encode($report->toArray(), self::FLAGS);
    }

    /**
     * The reviewable domain map that domain:check and later migration steps read.
     */
    public function formatDomainMap(BoundaryMap $map): string
    {
        return json_encode($map->toArray(), self::FLAGS);
    }

    public function formatCheckResult(BoundaryCheckResult $result): string
    {
        return json_encode($result->toArray(), self::FLAGS);
    }

    public function formatPlan(MigrationPlan $plan): string
    {
        return json_encode($plan->toArray(), self::FLAGS);
    }
}
