<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Enums\DependencyType;

final readonly class Dependency
{
    /**
     * @param  list<DependencyType>  $types  Ordered from strongest to weakest.
     */
    public function __construct(
        public string $source,
        public string $target,
        public array $types,
        public int $weight,
    ) {}
}
