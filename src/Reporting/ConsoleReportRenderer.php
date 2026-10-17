<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Reporting;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use MertBag\DomainMigrator\Data\AnalysisReport;
use MertBag\DomainMigrator\Data\ClassMetrics;
use MertBag\DomainMigrator\Data\SkippedFile;

final readonly class ConsoleReportRenderer
{
    public function render(AnalysisReport $report, OutputStyle $output, Factory $components): void
    {
        $components->info(sprintf(
            'Analyzed %d classes with %d internal dependencies.',
            count($report->graph->nodes),
            count($report->graph->edges),
        ));

        $this->renderKinds($report, $output, $components);
        $this->renderHotspots($report, $output);
        $this->renderSkippedFiles($report, $components);

        $output->writeln('  Run <fg=yellow>php artisan domain:analyze --format=json</> for the full dependency graph.');
        $output->newLine();
    }

    private function renderKinds(AnalysisReport $report, OutputStyle $output, Factory $components): void
    {
        $output->writeln('  <options=bold>Classes by kind</>');

        foreach ($report->kindCounts() as $entry) {
            $components->twoColumnDetail($entry['kind']->label(), (string) $entry['count']);
        }

        $isolated = $report->isolatedClassCount();

        if ($isolated > 0) {
            $components->twoColumnDetail('<fg=gray>Without internal dependencies</>', "<fg=gray>{$isolated}</>");
        }

        $output->newLine();
    }

    private function renderHotspots(AnalysisReport $report, OutputStyle $output): void
    {
        $hotspots = $report->hotspots();

        if ($hotspots === []) {
            return;
        }

        $output->writeln('  <options=bold>Coupling hot spots</> <fg=gray>(classes with the most dependents)</>');

        $output->table(
            ['Class', 'Kind', 'Dependents', 'Dependencies', 'Instability'],
            array_map(fn (ClassMetrics $metrics): array => [
                $metrics->class,
                $metrics->kind->label(),
                $metrics->afferent,
                $metrics->efferent,
                number_format($metrics->instability, 2),
            ], $hotspots),
        );
    }

    private function renderSkippedFiles(AnalysisReport $report, Factory $components): void
    {
        if ($report->skippedFiles === []) {
            return;
        }

        $components->warn(sprintf(
            'Skipped %d %s that could not be parsed:',
            count($report->skippedFiles),
            count($report->skippedFiles) === 1 ? 'file' : 'files',
        ));

        $components->bulletList(array_map(
            fn (SkippedFile $file): string => "{$file->file} <fg=gray>({$file->reason})</>",
            $report->skippedFiles,
        ));
    }
}
