<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Analysis\Graph\DependencyGraph;

final readonly class ScanResult
{
    /**
     * @param  list<SkippedFile>  $skippedFiles
     */
    public function __construct(
        public DependencyGraph $graph,
        public array $skippedFiles,
    ) {}
}
