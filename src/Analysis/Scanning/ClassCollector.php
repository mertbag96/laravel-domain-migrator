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

    public function __construct(
        public readonly string $name,
        public readonly DeclarationType $declaration,
    ) {}

    public function add(?string $target, DependencyType $type): void
    {
        if ($target === null || $target === $this->name) {
            return;
        }

        $this->dependencies[$target][$type->value] = $type;
    }

    /**
     * @param  list<string>  $imports
     */
    public function toScannedClass(string $file, array $imports): ScannedClass
    {
        $dependencies = $this->dependencies;

        foreach ($imports as $import) {
            if ($import !== $this->name) {
                $dependencies[$import][DependencyType::Import->value] = DependencyType::Import;
            }
        }

        ksort($dependencies);

        return new ScannedClass(
            name: $this->name,
            declaration: $this->declaration,
            file: $file,
            parent: $this->parent,
            interfaces: $this->interfaces,
            dependencies: array_map(array_values(...), $dependencies),
            handlerParameters: array_values(array_unique($this->handlerParameters)),
        );
    }
}
