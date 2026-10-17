<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

/**
 * A known violation, identified by its classes rather than a line so that edits elsewhere in a file keep it valid.
 */
final readonly class BaselineEntry
{
    public function __construct(
        public string $source,
        public string $target,
    ) {}

    public function key(): string
    {
        return $this->source.' -> '.$this->target;
    }

    /**
     * @return array{source: string, target: string}
     */
    public function toArray(): array
    {
        return ['source' => $this->source, 'target' => $this->target];
    }
}
