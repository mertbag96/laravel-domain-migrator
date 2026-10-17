<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Actions;

use MertBag\DomainMigrator\Boundaries\BoundaryChecker;
use MertBag\DomainMigrator\Data\AnalysisReport;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\Domain;
use MertBag\DomainMigrator\Data\DomainDefinition;

/**
 * Turns suggested domains into a domain map. A new map allows today's domain-to-domain dependencies so that
 * domain:check passes right away; an updated map keeps every reviewed assignment and rule, and only places
 * classes that are new or were unassigned. Updating a map from before schema 2 also adds depends_on.
 */
final readonly class BuildDomainMapAction
{
    public function __construct(
        private BoundaryChecker $checker,
    ) {}

    /**
     * @param  AnalysisReport  $report  When updating, analyzed with the existing map's domains as hints.
     */
    public function execute(AnalysisReport $report, ?BoundaryMap $existing = null): BoundaryMap
    {
        $present = $report->graph->nodes;

        if ($existing === null) {
            $map = new BoundaryMap(
                domains: array_map(fn (Domain $domain): DomainDefinition => new DomainDefinition($domain->name, $domain->classes), $report->domains->domains),
                shared: $report->domains->shared,
            );

            return $this->withDependencies($map, $report, array_map(fn (Domain $domain): string => $domain->name, $report->domains->domains));
        }

        $isNew = fn (string $class): bool => isset($present[$class]) && $existing->domainOf($class) === null;
        $added = [];

        foreach ($report->domains->domains as $domain) {
            $added[$domain->name] = array_values(array_filter($domain->classes, $isNew));
        }

        $domains = [];

        foreach ($existing->domains as $domain) {
            $domains[] = new DomainDefinition(
                name: $domain->name,
                classes: $this->sorted([
                    ...array_filter($domain->classes, fn (string $class): bool => isset($present[$class])),
                    ...$added[$domain->name] ?? [],
                ]),
                namespaces: $domain->namespaces,
                dependsOn: $domain->dependsOn,
            );

            unset($added[$domain->name]);
        }

        $newDomains = array_keys(array_filter($added));

        foreach ($newDomains as $name) {
            $domains[] = new DomainDefinition($name, $this->sorted($added[$name]));
        }

        $map = new BoundaryMap(
            domains: $domains,
            shared: $this->sorted([
                ...array_filter($existing->shared, fn (string $class): bool => isset($present[$class])),
                ...array_filter($report->domains->shared, $isNew),
            ]),
        );

        return $this->withDependencies($map, $report, $existing->isOutdated()
            ? array_map(fn (DomainDefinition $domain): string => $domain->name, $domains)
            : $newDomains);
    }

    /**
     * Fills in depends_on for the given domains and lists every class that ended up in no domain as unassigned.
     *
     * @param  list<string>  $domainNames
     */
    private function withDependencies(BoundaryMap $map, AnalysisReport $report, array $domainNames): BoundaryMap
    {
        $dependencies = $this->checker->dependencies($report->graph, $map);

        return new BoundaryMap(
            domains: array_map(fn (DomainDefinition $domain): DomainDefinition => in_array($domain->name, $domainNames, true)
                ? new DomainDefinition($domain->name, $domain->classes, $domain->namespaces, $dependencies[$domain->name] ?? [])
                : $domain, $map->domains),
            shared: $map->shared,
            unassigned: array_values(array_filter(array_keys($report->graph->nodes), fn (string $class): bool => ! $map->isMapped($class))),
        );
    }

    /**
     * @param  array<string>  $classes
     * @return list<string>
     */
    private function sorted(array $classes): array
    {
        $classes = array_values(array_unique($classes));
        sort($classes);

        return $classes;
    }
}
