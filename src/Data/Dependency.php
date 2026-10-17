<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Enums\DependencyType;

final readonly class Dependency
{
    /**
     * @param  list<DependencyType>  $types  Ordered from strongest to weakest.
     * @param  ?int  $line  Line in the source class's file where the target is first referenced.
     */
    public function __construct(
        public string $source,
        public string $target,
        public array $types,
        public int $weight,
        public ?int $line = null,
    ) {}
}
