<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Actions;

use MertBag\DomainMigrator\Analysis\Graph\GraphBuilder;
use MertBag\DomainMigrator\Analysis\Scanning\ClassScanner;
use MertBag\DomainMigrator\Analysis\Scanning\SourceFileFinder;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\ScanResult;
use MertBag\DomainMigrator\Data\SkippedFile;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;
use MertBag\DomainMigrator\Exceptions\UnparsableFileException;

final readonly class ScanApplicationAction
{
    public function __construct(
        private SourceFileFinder $finder,
        private ClassScanner $scanner,
        private GraphBuilder $graphBuilder,
    ) {}

    /**
     * @throws InvalidConfigurationException
     */
    public function execute(AnalysisOptions $options): ScanResult
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

        return new ScanResult($this->graphBuilder->build($classes, $options->weights), $skippedFiles);
    }
}
