<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Reporting;

use MertBag\DomainMigrator\Data\AnalysisReport;
use MertBag\DomainMigrator\Data\DomainMap;

final readonly class JsonReportFormatter
{
    public const int MAP_SCHEMA_VERSION = 1;

    private const int FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    public function format(AnalysisReport $report): string
    {
        return json_encode($report->toArray(), self::FLAGS);
    }

    /**
     * The reviewable domain map that later migration steps read.
     */
    public function formatDomainMap(DomainMap $map): string
    {
        return json_encode(['schema' => self::MAP_SCHEMA_VERSION, ...$map->toArray()], self::FLAGS);
    }
}
