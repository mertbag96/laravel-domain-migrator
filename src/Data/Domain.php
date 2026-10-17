<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

final readonly class Domain
{
    /**
     * @param  list<string>  $classes
     * @param  float  $cohesion  Share of the domain's dependency weight that stays inside it, from 0 to 1.
     * @param  bool  $pinned  Whether the domain was seeded by a configured hint.
     */
    public function __construct(
        public string $name,
        public array $classes,
        public float $cohesion,
        public bool $pinned = false,
    ) {}
}
