<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Boundaries;

use MertBag\DomainMigrator\Analysis\Graph\DependencyGraph;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\DependencyWeights;
use MertBag\DomainMigrator\Data\DomainDefinition;
use MertBag\DomainMigrator\Data\DomainMap;
use MertBag\DomainMigrator\Data\ScannedClass;
use MertBag\DomainMigrator\Data\Violation;
use MertBag\DomainMigrator\Enums\DependencyType;

/**
 * Finds dependencies that cross from a checked domain into another domain it does not list in depends_on.
 * Shared classes may be used by every domain; unassigned and unmapped classes are not checked.
 */
final readonly class BoundaryChecker
{
    /**
     * @return list<Violation>
     */
    public function check(DependencyGraph $graph, BoundaryMap $map): array
    {
        $violations = [];

        foreach ($graph->edges as $edge) {
            $violation = $this->violation($edge->source, $edge->target, $edge->types, $graph->nodes[$edge->source]->file, $edge->line, $map);

            if ($violation !== null) {
                $violations[] = $violation;
            }
        }

        return $violations;
    }

    /**
     * Checks a single scanned class, for tools that analyze one file at a time such as PHPStan.
     *
     * @return list<Violation>
     */
    public function checkClass(ScannedClass $class, BoundaryMap $map, DependencyWeights $weights = new DependencyWeights): array
    {
        $dependencies = $class->dependencies;

        foreach ($class->handlerParameters as $parameter) {
            $dependencies[$parameter][] = DependencyType::Usage;
        }

        $violations = [];

        foreach ($dependencies as $target => $types) {
            $types = array_values(array_column(array_map(fn (DependencyType $type): array => [$type->value, $type], $types), 1, 0));
            usort($types, fn (DependencyType $a, DependencyType $b): int => $weights->of($b) <=> $weights->of($a));

            $violation = $this->violation($class->name, $target, $types, $class->file, $class->lines[$target] ?? null, $map);

            if ($violation !== null) {
                $violations[] = $violation;
            }
        }

        return $violations;
    }

    /**
     * @param  list<DependencyType>  $types
     */
    private function violation(string $source, string $target, array $types, string $file, ?int $line, BoundaryMap $map): ?Violation
    {
        $sourceDomain = $map->domainOf($source);
        $targetDomain = $map->domainOf($target);

        if (
            $sourceDomain === null
            || $targetDomain === null
            || $sourceDomain === $targetDomain
            || $sourceDomain === DomainMap::SHARED
            || $targetDomain === DomainMap::SHARED
            || $map->domain($sourceDomain)?->mayUse($targetDomain) !== false
        ) {
            return null;
        }

        return new Violation($source, $target, $sourceDomain, $targetDomain, $file, $line, $types);
    }

    /**
     * Today's domain-to-domain dependencies, used to fill in depends_on for a new map.
     *
     * @return array<string, list<string>> Domain => domains it uses, sorted.
     */
    public function dependencies(DependencyGraph $graph, BoundaryMap $map): array
    {
        $dependencies = array_fill_keys(array_map(fn (DomainDefinition $domain): string => $domain->name, $map->domains), []);

        foreach ($graph->edges as $edge) {
            $sourceDomain = $map->domainOf($edge->source);
            $targetDomain = $map->domainOf($edge->target);

            if (
                $sourceDomain !== null
                && $targetDomain !== null
                && $sourceDomain !== $targetDomain
                && isset($dependencies[$sourceDomain], $dependencies[$targetDomain])
            ) {
                $dependencies[$sourceDomain][$targetDomain] = $targetDomain;
            }
        }

        return array_map(function (array $domains): array {
            sort($domains);

            return $domains;
        }, $dependencies);
    }
}
