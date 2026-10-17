<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

final readonly class SkippedFile
{
    public function __construct(
        public string $file,
        public string $reason,
    ) {}
}
