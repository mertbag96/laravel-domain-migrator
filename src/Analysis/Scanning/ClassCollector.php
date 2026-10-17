<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Analysis\Scanning;

use MertBag\DomainMigrator\Data\ScannedClass;
use MertBag\DomainMigrator\Enums\DeclarationType;
use MertBag\DomainMigrator\Enums\DependencyType;

/**
 * @internal
 */
final class ClassCollector
{
    public ?string $parent = null;

    /** @var list<string> */
    public array $interfaces = [];

    /** @var list<string> */
    public array $handlerParameters = [];

    /** @var array<string, array<string, DependencyType>> */
    private array $dependencies = [];

    /** @var array<string, int> */
    private array $lines = [];

    public function __construct(
        public readonly string $name,
        public readonly DeclarationType $declaration,
    ) {}

    public function add(?string $target, DependencyType $type, int $line): void
    {
        if ($target === null || $target === $this->name) {
            return;
        }

        $this->dependencies[$target][$type->value] = $type;
        $this->lines[$target] = min($this->lines[$target] ?? PHP_INT_MAX, $line);
    }

    /**
     * @param  array<string, int>  $imports  Imported class => line of its use statement.
     */
    public function toScannedClass(string $file, array $imports): ScannedClass
    {
        $dependencies = $this->dependencies;
        $lines = $this->lines;

        foreach ($imports as $import => $line) {
            if ($import !== $this->name) {
                $dependencies[$import][DependencyType::Import->value] = DependencyType::Import;
                $lines[$import] = min($lines[$import] ?? PHP_INT_MAX, $line);
            }
        }

        ksort($dependencies);
        ksort($lines);

        return new ScannedClass(
            name: $this->name,
            declaration: $this->declaration,
            file: $file,
            parent: $this->parent,
            interfaces: $this->interfaces,
            dependencies: array_map(array_values(...), $dependencies),
            handlerParameters: array_values(array_unique($this->handlerParameters)),
            lines: $lines,
        );
    }
}
