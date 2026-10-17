<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Analysis\Graph;

use MertBag\DomainMigrator\Data\ClassNode;
use MertBag\DomainMigrator\Data\Dependency;

final readonly class DependencyGraph
{
    /** @var array<string, list<string>> */
    private array $dependents;

    /** @var array<string, list<string>> */
    private array $dependencies;

    /**
     * @param  array<string, ClassNode>  $nodes  Keyed by fully qualified class name.
     * @param  list<Dependency>  $edges
     */
    public function __construct(
        public array $nodes,
        public array $edges,
    ) {
        $dependents = [];
        $dependencies = [];

        foreach ($edges as $edge) {
            $dependents[$edge->target][] = $edge->source;
            $dependencies[$edge->source][] = $edge->target;
        }

        $this->dependents = $dependents;
        $this->dependencies = $dependencies;
    }

    /**
     * @return list<string>
     */
    public function dependentsOf(string $class): array
    {
        return $this->dependents[$class] ?? [];
    }

    /**
     * @return list<string>
     */
    public function dependenciesOf(string $class): array
    {
        return $this->dependencies[$class] ?? [];
    }
}
