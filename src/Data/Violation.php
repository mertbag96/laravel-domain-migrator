<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Enums\DependencyType;

/**
 * A dependency from one domain to another that the source domain does not list in depends_on.
 */
final readonly class Violation
{
    /**
     * @param  list<DependencyType>  $types  Ordered from strongest to weakest.
     */
    public function __construct(
        public string $source,
        public string $target,
        public string $sourceDomain,
        public string $targetDomain,
        public string $file,
        public ?int $line,
        public array $types,
    ) {}

    public function entry(): BaselineEntry
    {
        return new BaselineEntry($this->source, $this->target);
    }

    /**
     * @return array{source: string, target: string, source_domain: string, target_domain: string, file: string, line: ?int, types: list<string>}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'target' => $this->target,
            'source_domain' => $this->sourceDomain,
            'target_domain' => $this->targetDomain,
            'file' => $this->file,
            'line' => $this->line,
            'types' => array_map(fn (DependencyType $type): string => $type->value, $this->types),
        ];
    }
}
