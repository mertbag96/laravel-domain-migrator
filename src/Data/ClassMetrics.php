<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Enums\ClassKind;

final readonly class ClassMetrics
{
    /**
     * @param  int  $afferent  Number of classes that depend on this class.
     * @param  int  $efferent  Number of classes this class depends on.
     * @param  float  $instability  efferent / (afferent + efferent), from 0 (stable) to 1 (unstable).
     */
    public function __construct(
        public string $class,
        public ClassKind $kind,
        public int $afferent,
        public int $efferent,
        public float $instability,
    ) {}

    public function isIsolated(): bool
    {
        return $this->afferent === 0 && $this->efferent === 0;
    }
}
