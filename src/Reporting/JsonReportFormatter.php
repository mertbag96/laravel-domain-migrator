<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Reporting;

use MertBag\DomainMigrator\Data\AnalysisReport;

final readonly class JsonReportFormatter
{
    public function format(AnalysisReport $report): string
    {
        return json_encode(
            $report->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );
    }
}
