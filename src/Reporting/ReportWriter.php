<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Reporting;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use MertBag\DomainMigrator\Data\AnalysisReport;
use MertBag\DomainMigrator\Data\DomainMap;
use MertBag\DomainMigrator\Enums\ReportFormat;

final readonly class ReportWriter
{
    public function __construct(
        private Filesystem $files,
        private JsonReportFormatter $json,
        private HtmlReportRenderer $html,
    ) {}

    public function render(AnalysisReport $report, ReportFormat $format): string
    {
        return match ($format) {
            ReportFormat::Json => $this->json->format($report),
            ReportFormat::Html => $this->html->render($report),
            ReportFormat::Console => throw new InvalidArgumentException('Console reports are rendered by the ConsoleReportRenderer.'),
        };
    }

    public function write(AnalysisReport $report, ReportFormat $format, string $path): void
    {
        $this->files->put($path, $this->render($report, $format).PHP_EOL);
    }

    public function writeDomainMap(DomainMap $map, string $path): void
    {
        $this->files->put($path, $this->json->formatDomainMap($map).PHP_EOL);
    }
}
