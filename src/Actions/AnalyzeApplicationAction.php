<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Actions;

use MertBag\DomainMigrator\Analysis\Clustering\DomainDetector;
use MertBag\DomainMigrator\Analysis\Graph\GraphBuilder;
use MertBag\DomainMigrator\Analysis\Metrics\MetricsCalculator;
use MertBag\DomainMigrator\Analysis\Scanning\ClassScanner;
use MertBag\DomainMigrator\Analysis\Scanning\SourceFileFinder;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\AnalysisReport;
use MertBag\DomainMigrator\Data\SkippedFile;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;
use MertBag\DomainMigrator\Exceptions\UnparsableFileException;

final readonly class AnalyzeApplicationAction
{
    public function __construct(
        private SourceFileFinder $finder,
        private ClassScanner $scanner,
        private GraphBuilder $graphBuilder,
        private MetricsCalculator $metricsCalculator,
        private DomainDetector $domainDetector,
    ) {}

    /**
     * @throws InvalidConfigurationException
     */
    public function execute(AnalysisOptions $options): AnalysisReport
    {
        $classes = [];
        $skippedFiles = [];

        foreach ($this->finder->find($options) as $file) {
            try {
                array_push($classes, ...$this->scanner->scan($file));
            } catch (UnparsableFileException $exception) {
                $skippedFiles[] = new SkippedFile($file->relativePath, $exception->getMessage());
            }
        }

        $graph = $this->graphBuilder->build($classes, $options->weights);

        return new AnalysisReport(
            graph: $graph,
            metrics: $this->metricsCalculator->calculate($graph),
            skippedFiles: $skippedFiles,
            hotspotLimit: $options->hotspotLimit,
            domains: $this->domainDetector->detect($graph, $options->clustering, $options->weights),
        );
    }
}
