<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Analysis\Graph;

use MertBag\DomainMigrator\Data\ClassNode;
use MertBag\DomainMigrator\Data\Dependency;
use MertBag\DomainMigrator\Data\DependencyWeights;
use MertBag\DomainMigrator\Data\ScannedClass;
use MertBag\DomainMigrator\Enums\ClassKind;
use MertBag\DomainMigrator\Enums\DependencyType;

/**
 * Builds a graph of dependencies between scanned classes only; references to
 * framework or vendor classes are dropped.
 */
final readonly class GraphBuilder
{
    /**
     * @param  list<ScannedClass>  $classes
     */
    public function build(array $classes, DependencyWeights $weights): DependencyGraph
    {
        $kinds = new KindResolver($classes);
        $nodes = [];

        foreach ($classes as $class) {
            $nodes[$class->name] ??= new ClassNode($class->name, $kinds->resolve($class), $class->declaration, $class->file);
        }

        ksort($nodes);

        $edges = [];
        $lines = [];

        foreach ($classes as $class) {
            foreach ($this->dependenciesOf($class, $nodes[$class->name]->kind) as $target => $types) {
                if ($target === $class->name || ! isset($nodes[$target])) {
                    continue;
                }

                foreach ($types as $type) {
                    $edges[$class->name][$target][$type->value] = $type;
                }

                if (isset($class->lines[$target])) {
                    $lines[$class->name][$target] ??= $class->lines[$target];
                }
            }
        }

        return new DependencyGraph($nodes, $this->toDependencies($edges, $lines, $weights));
    }

    /**
     * @return array<string, list<DependencyType>>
     */
    private function dependenciesOf(ScannedClass $class, ClassKind $kind): array
    {
        $dependencies = $class->dependencies;
        $handlerType = $kind === ClassKind::Listener ? DependencyType::FrameworkBinding : DependencyType::Usage;

        foreach ($class->handlerParameters as $parameter) {
            $dependencies[$parameter][] = $handlerType;
        }

        return $dependencies;
    }

    /**
     * @param  array<string, array<string, array<string, DependencyType>>>  $edges
     * @param  array<string, array<string, int>>  $lines
     * @return list<Dependency>
     */
    private function toDependencies(array $edges, array $lines, DependencyWeights $weights): array
    {
        $dependencies = [];

        ksort($edges);

        foreach ($edges as $source => $targets) {
            ksort($targets);

            foreach ($targets as $target => $types) {
                $types = array_values($types);
                usort($types, fn (DependencyType $a, DependencyType $b): int => $weights->of($b) <=> $weights->of($a));

                $dependencies[] = new Dependency(
                    source: $source,
                    target: $target,
                    types: $types,
                    weight: array_reduce(
                        $types,
                        fn (int $weight, DependencyType $type): int => max($weight, $weights->of($type)),
                        0,
                    ),
                    line: $lines[$source][$target] ?? null,
                );
            }
        }

        return $dependencies;
    }
}
