<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Boundaries;

use MertBag\DomainMigrator\Analysis\Graph\DependencyGraph;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\DomainDefinition;
use MertBag\DomainMigrator\Data\DomainMap;
use MertBag\DomainMigrator\Data\Violation;

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
            $sourceDomain = $map->domainOf($edge->source);
            $targetDomain = $map->domainOf($edge->target);

            if (
                $sourceDomain === null
                || $targetDomain === null
                || $sourceDomain === $targetDomain
                || $sourceDomain === DomainMap::SHARED
                || $targetDomain === DomainMap::SHARED
                || $map->domain($sourceDomain)?->mayUse($targetDomain) !== false
            ) {
                continue;
            }

            $violations[] = new Violation(
                source: $edge->source,
                target: $edge->target,
                sourceDomain: $sourceDomain,
                targetDomain: $targetDomain,
                file: $graph->nodes[$edge->source]->file,
                line: $edge->line,
                types: $edge->types,
            );
        }

        return $violations;
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
