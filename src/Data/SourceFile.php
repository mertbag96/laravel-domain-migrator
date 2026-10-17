<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

final readonly class SourceFile
{
    public function __construct(
        public string $absolutePath,
        public string $relativePath,
    ) {}
}
