<?php

declare(strict_types=1);

/**
 * Prints the suggested domains for any Laravel app without installing the package in it.
 *
 * Usage: php scripts/domains.php /path/to/app [--html=report.html]
 */

use MertBag\DomainMigrator\Actions\AnalyzeApplicationAction;
use MertBag\DomainMigrator\Actions\ScanApplicationAction;
use MertBag\DomainMigrator\Analysis\Clustering\DomainDetector;
use MertBag\DomainMigrator\Analysis\Graph\GraphBuilder;
use MertBag\DomainMigrator\Analysis\Metrics\MetricsCalculator;
use MertBag\DomainMigrator\Analysis\Scanning\ClassScanner;
use MertBag\DomainMigrator\Analysis\Scanning\SourceFileFinder;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Reporting\HtmlReportRenderer;

require __DIR__.'/../vendor/autoload.php';

$basePath = $argv[1] ?? null;

if ($basePath === null || ! is_dir($basePath)) {
    fwrite(STDERR, "Usage: php scripts/domains.php /path/to/app [--html=report.html]\n");
    exit(1);
}

$htmlPath = null;

foreach (array_slice($argv, 2) as $argument) {
    if (str_starts_with($argument, '--html=')) {
        $htmlPath = substr($argument, 7);
    }
}

$started = microtime(true);

$report = (new AnalyzeApplicationAction(
    new ScanApplicationAction(new SourceFileFinder, new ClassScanner, new GraphBuilder),
    new MetricsCalculator,
    new DomainDetector,
))->execute(AnalysisOptions::fromConfig(require __DIR__.'/../config/domain-migrator.php', realpath($basePath) ?: $basePath));

$namespaceOf = static function (string $class): string {
    $parts = explode('\\', $class);

    return implode('\\', array_slice($parts, 1, max(1, min(2, count($parts) - 2))));
};

printf(
    "%d classes, %d dependencies, %d domains, %d shared, %d unassigned (%.2fs)\n\n",
    count($report->graph->nodes),
    count($report->graph->edges),
    count($report->domains->domains),
    count($report->domains->shared),
    count($report->domains->unassigned),
    microtime(true) - $started,
);

foreach ($report->domains->domains as $domain) {
    $namespaces = array_count_values(array_map($namespaceOf, $domain->classes));
    arsort($namespaces);

    $models = array_filter($domain->classes, fn (string $class): bool => $report->graph->nodes[$class]->kind->value === 'model');

    printf("%-28s %4d  %3d%%  %s\n", $domain->name, count($domain->classes), (int) round($domain->cohesion * 100), implode(', ', array_map(
        fn (string $namespace, int $count): string => "{$namespace}({$count})",
        array_keys(array_slice($namespaces, 0, 4, true)),
        array_slice($namespaces, 0, 4, true),
    )));

    if ($models !== []) {
        printf("%-28s             models: %s\n", '', implode(', ', array_map(class_basename(...), array_slice(array_values($models), 0, 12))));
    }
}

printf("\nShared: %s\n", implode(', ', array_map(class_basename(...), $report->domains->shared)));

if ($htmlPath !== null) {
    file_put_contents($htmlPath, (new HtmlReportRenderer)->render($report));
    echo "\nHTML report written to {$htmlPath}\n";
}
