<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Actions;

use MertBag\DomainMigrator\Analysis\Clustering\DomainDetector;
use MertBag\DomainMigrator\Analysis\Metrics\MetricsCalculator;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\AnalysisReport;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;

final readonly class AnalyzeApplicationAction
{
    public function __construct(
        private ScanApplicationAction $scan,
        private MetricsCalculator $metricsCalculator,
        private DomainDetector $domainDetector,
    ) {}

    /**
     * @throws InvalidConfigurationException
     */
    public function execute(AnalysisOptions $options): AnalysisReport
    {
        $scan = $this->scan->execute($options);

        return new AnalysisReport(
            graph: $scan->graph,
            metrics: $this->metricsCalculator->calculate($scan->graph),
            skippedFiles: $scan->skippedFiles,
            hotspotLimit: $options->hotspotLimit,
            domains: $this->domainDetector->detect($scan->graph, $options->clustering, $options->weights),
        );
    }
}
