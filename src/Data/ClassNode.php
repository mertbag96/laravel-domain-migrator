<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Enums\ClassKind;
use MertBag\DomainMigrator\Enums\DeclarationType;

final readonly class ClassNode
{
    public function __construct(
        public string $name,
        public ClassKind $kind,
        public DeclarationType $declaration,
        public string $file,
    ) {}
}
