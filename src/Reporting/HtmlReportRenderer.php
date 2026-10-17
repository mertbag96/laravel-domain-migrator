<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Reporting;

use LogicException;
use MertBag\DomainMigrator\Data\AnalysisReport;

final readonly class HtmlReportRenderer
{
    private const string TEMPLATE = __DIR__.'/../../resources/report.html';

    private const string PLACEHOLDER = '{{REPORT_JSON}}';

    /**
     * Renders a self-contained HTML page; the report data is embedded as JSON
     * and escaped so it cannot close the surrounding script tag.
     */
    public function render(AnalysisReport $report): string
    {
        $template = @file_get_contents(self::TEMPLATE);

        if ($template === false) {
            throw new LogicException('The HTML report template is missing from the package.');
        }

        $json = json_encode(
            $report->toArray(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return str_replace(self::PLACEHOLDER, $json, $template);
    }
}
