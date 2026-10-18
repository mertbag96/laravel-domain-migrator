<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Scanning;

use MertBag\DomainMigrator\Data\ClassFacts;

/**
 * @internal
 */
final class FactsCollector
{
    public ?string $parent = null;

    /** @var list<string> */
    public array $interfaces = [];

    /** @var list<string> */
    public array $traits = [];

    /** @var list<string> */
    public array $methods = [];

    /** @var list<string> */
    public array $attributes = [];

    /** @var array<string, int> */
    public array $relationCalls = [];

    /** @var list<string> */
    public array $morphedBy = [];

    /** @var list<string> */
    public array $channels = [];

    public ?string $modelProperty = null;

    public ?string $currentMethod = null;

    public function __construct(
        public readonly string $name,
        public readonly int $line,
    ) {}

    public function toFacts(string $file): ClassFacts
    {
        return new ClassFacts(
            name: $this->name,
            file: $file,
            line: $this->line,
            parent: $this->parent,
            interfaces: $this->interfaces,
            traits: array_values(array_unique($this->traits)),
            methods: $this->methods,
            attributes: array_values(array_unique($this->attributes)),
            relationCalls: $this->relationCalls,
            morphedBy: array_values(array_unique($this->morphedBy)),
            channels: array_values(array_unique($this->channels)),
            modelProperty: $this->modelProperty,
        );
    }
}
