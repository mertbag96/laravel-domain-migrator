<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Analysis\Clustering;

use Illuminate\Support\Str;
use MertBag\DomainMigrator\Analysis\Graph\DependencyGraph;
use MertBag\DomainMigrator\Data\ClusteringOptions;
use MertBag\DomainMigrator\Data\DependencyWeights;
use MertBag\DomainMigrator\Data\Domain;
use MertBag\DomainMigrator\Data\DomainMap;
use MertBag\DomainMigrator\Enums\ClassKind;
use MertBag\DomainMigrator\Enums\DependencyType;

/**
 * Suggests domains by clustering the dependency graph in two passes.
 *
 * Inheriting from a widely used base type (a base controller, form request or
 * trait) says which layer a class belongs to, not which domain, so those links
 * are ignored. The first pass then finds non-model classes used across many
 * clusters; they form the shared kernel and are left out of the second pass so
 * they cannot glue domains together.
 */
final readonly class DomainDetector
{
    private const array NAME_SUFFIXES = [
        'Controller', 'Action', 'Service', 'Repository', 'Job', 'Event', 'Listener', 'Policy', 'Observer',
        'Request', 'Resource', 'Response', 'Notification', 'Exception', 'Command', 'Builder', 'Data',
    ];

    private const array TECHNICAL_SEGMENTS = [
        'Actions', 'Admin', 'Api', 'Builders', 'Casts', 'Commands', 'Components', 'Concerns', 'Console',
        'Contracts', 'Controllers', 'Data', 'Domain', 'Enums', 'Events', 'Exceptions', 'Facades', 'Filament',
        'Helpers', 'Http', 'Interfaces', 'Jobs', 'Listeners', 'Livewire', 'Mail', 'Middleware', 'Models',
        'Notifications', 'Observers', 'Policies', 'Presenters', 'Providers', 'Repositories', 'Requests', 'Resources',
        'Responses', 'Rules', 'Services', 'Support', 'Traits', 'Transformers', 'V1', 'V2', 'V3', 'Values', 'View',
    ];

    private const float NAMESPACE_NAME_MIN_SHARE = 0.4;

    private const float NAMESPACE_NAME_PREFERRED_SHARE = 0.6;

    public function detect(
        DependencyGraph $graph,
        ClusteringOptions $options,
        DependencyWeights $weights = new DependencyWeights,
    ): DomainMap {
        $hints = $this->hintedClasses($graph, $options);
        $adjacency = $this->clusteringAdjacency($graph, $options, $weights);
        $louvain = new Louvain($options->resolution);

        $fixedShared = array_values(array_filter($options->shared, fn (string $class): bool => isset($graph->nodes[$class]) && ! isset($hints[$class])));
        $adjacency = $this->withoutNodes($adjacency, $fixedShared);

        $shared = [...$fixedShared, ...$this->sharedClasses($graph, $louvain->partition($adjacency, $hints), $hints, $options->sharedMinDomains)];
        sort($shared);
        $adjacency = $this->withoutNodes($adjacency, $shared);

        $communities = [];

        foreach ($louvain->partition($adjacency, $hints) as $class => $community) {
            $communities[$community][] = $class;
        }

        usort($communities, fn (array $a, array $b): int => [count($b), $a[0]] <=> [count($a), $b[0]]);

        $domains = [];
        $unassigned = [];
        $usedNames = array_fill_keys(array_map($this->nameKey(...), array_keys($options->hints)), true);

        foreach ($communities as $members) {
            $label = $this->hintLabel($members, $hints);

            if ($label === null && count($members) === 1) {
                $unassigned[] = $members[0];

                continue;
            }

            $domains[] = new Domain(
                name: $label ?? $this->suggestName($graph, $adjacency, $members, $usedNames),
                classes: $members,
                cohesion: $this->cohesion($adjacency, $members),
                pinned: $label !== null,
            );
        }

        sort($unassigned);

        return new DomainMap($domains, $shared, $unassigned);
    }

    /**
     * @return array<string, string> Class => domain name, limited to classes in the graph.
     */
    private function hintedClasses(DependencyGraph $graph, ClusteringOptions $options): array
    {
        $hinted = [];

        foreach ($options->hints as $domain => $classes) {
            foreach ($classes as $class) {
                if (isset($graph->nodes[$class])) {
                    $hinted[$class] ??= $domain;
                }
            }
        }

        return $hinted;
    }

    /**
     * Undirected weights between classes, without links that only express
     * inheritance from a technical base type.
     *
     * @return array<string, array<string, float>>
     */
    private function clusteringAdjacency(DependencyGraph $graph, ClusteringOptions $options, DependencyWeights $weights): array
    {
        $children = [];

        foreach ($graph->edges as $edge) {
            if (in_array(DependencyType::Inheritance, $edge->types, true)) {
                $children[$edge->target][] = $edge->source;
            }
        }

        $baseTypes = [];

        foreach ($children as $parent => $inheritors) {
            if (count($inheritors) >= $options->baseTypeThreshold && ! $this->isFeatureHierarchy($parent, $inheritors)) {
                $baseTypes[$parent] = true;
            }
        }

        $adjacency = array_fill_keys(array_keys($graph->nodes), []);

        foreach ($graph->edges as $edge) {
            $types = $edge->types;

            if (isset($baseTypes[$edge->target])) {
                $types = array_filter($types, fn (DependencyType $type): bool => $type !== DependencyType::Inheritance && $type !== DependencyType::Import);
            }

            $weight = array_reduce($types, fn (int $carry, DependencyType $type): int => max($carry, $weights->of($type)), 0);

            if ($weight === 0) {
                continue;
            }

            $adjacency[$edge->source][$edge->target] = ($adjacency[$edge->source][$edge->target] ?? 0.0) + $weight;
            $adjacency[$edge->target][$edge->source] = ($adjacency[$edge->target][$edge->source] ?? 0.0) + $weight;
        }

        return $adjacency;
    }

    /**
     * A hierarchy such as App\Models\Labels\Label with all of its subclasses under
     * App\Models\Labels describes one feature, unlike a base controller or trait
     * that every layer extends.
     *
     * @param  list<string>  $inheritors
     */
    private function isFeatureHierarchy(string $parent, array $inheritors): bool
    {
        $namespace = substr($parent, 0, (int) strrpos($parent, '\\'));
        $segments = array_slice(explode('\\', $namespace), 1);

        if (array_diff($segments, self::TECHNICAL_SEGMENTS) === []) {
            return false;
        }

        foreach ($inheritors as $inheritor) {
            if (! str_starts_with($inheritor, $namespace.'\\')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, int>  $partition
     * @param  array<string, string>  $hints
     * @return list<string>
     */
    private function sharedClasses(DependencyGraph $graph, array $partition, array $hints, int $minDomains): array
    {
        $shared = [];

        foreach ($partition as $class => $community) {
            if (isset($hints[$class]) || $graph->nodes[$class]->kind === ClassKind::Model) {
                continue;
            }

            $otherCommunities = [];

            foreach ($graph->dependentsOf($class) as $dependent) {
                if (isset($partition[$dependent]) && $partition[$dependent] !== $community) {
                    $otherCommunities[$partition[$dependent]] = true;
                }
            }

            if (count($otherCommunities) >= $minDomains) {
                $shared[] = $class;
            }
        }

        sort($shared);

        return $shared;
    }

    /**
     * @param  array<string, array<string, float>>  $adjacency
     * @param  list<string>  $nodes
     * @return array<string, array<string, float>>
     */
    private function withoutNodes(array $adjacency, array $nodes): array
    {
        $removed = array_flip($nodes);
        $adjacency = array_diff_key($adjacency, $removed);

        return array_map(fn (array $neighbours): array => array_diff_key($neighbours, $removed), $adjacency);
    }

    /**
     * @param  list<string>  $members
     * @param  array<string, string>  $hints
     */
    private function hintLabel(array $members, array $hints): ?string
    {
        foreach ($members as $member) {
            if (isset($hints[$member])) {
                return $hints[$member];
            }
        }

        return null;
    }

    /**
     * Names the domain after, in order of preference: a namespace nearly all of
     * its classes share, its most connected model, a namespace many of its classes
     * share, the model it depends on most, or its most connected class. Names
     * already taken by larger domains are skipped.
     *
     * @param  array<string, array<string, float>>  $adjacency
     * @param  list<string>  $members
     * @param  array<string, true>  $usedNames
     */
    private function suggestName(DependencyGraph $graph, array $adjacency, array $members, array &$usedNames): string
    {
        $isModel = fn (string $class): bool => $graph->nodes[$class]->kind === ClassKind::Model && ! str_ends_with($class, 'Pivot');
        $inside = array_flip($members);
        $weights = [];
        $neighbours = [];

        foreach ($members as $member) {
            foreach ($adjacency[$member] as $neighbour => $weight) {
                $weights[$neighbour] = ($weights[$neighbour] ?? 0.0) + $weight;

                if (! isset($inside[$neighbour])) {
                    $neighbours[] = $neighbour;
                }
            }
        }

        $names = [
            ...$this->namespaceNames($members, self::NAMESPACE_NAME_PREFERRED_SHARE),
            ...array_map($this->nameFor(...), $this->rankByWeight(array_filter($members, $isModel), $weights)),
            ...$this->namespaceNames($members, self::NAMESPACE_NAME_MIN_SHARE),
            ...array_map($this->nameFor(...), $this->rankByWeight(array_filter(array_unique($neighbours), $isModel), $weights)),
            ...array_map($this->nameFor(...), $this->rankByWeight($members, $weights)),
        ];

        $name = current(array_filter($names, fn (string $name): bool => ! isset($usedNames[$this->nameKey($name)])))
            ?: $this->numbered($names[0], $usedNames);

        $usedNames[$this->nameKey($name)] = true;

        return $name;
    }

    /**
     * "User" and "Users" would read as the same domain, so names are compared in plural, lowercase form.
     */
    private function nameKey(string $name): string
    {
        return Str::lower(Str::plural($name));
    }

    /**
     * Non-technical namespace segments shared by a large part of the domain, most common first.
     *
     * @param  list<string>  $members
     * @return list<string>
     */
    private function namespaceNames(array $members, float $minShare): array
    {
        $counts = [];

        foreach ($members as $member) {
            $segments = array_slice(explode('\\', $member), 1, -1);

            foreach (array_unique($segments) as $segment) {
                if (! in_array($segment, self::TECHNICAL_SEGMENTS, true)) {
                    $counts[$segment] = ($counts[$segment] ?? 0) + 1;
                }
            }
        }

        $counts = array_filter($counts, fn (int $count): bool => $count >= count($members) * $minShare);
        arsort($counts);

        return array_map(fn (int|string $segment): string => Str::studly((string) $segment), array_keys($counts));
    }

    /**
     * @param  array<array-key, string>  $classes
     * @param  array<string, float>  $weights
     * @return list<string>
     */
    private function rankByWeight(array $classes, array $weights): array
    {
        $classes = array_values($classes);

        usort($classes, fn (string $a, string $b): int => [$weights[$b] ?? 0.0, $a] <=> [$weights[$a] ?? 0.0, $b]);

        return $classes;
    }

    private function nameFor(string $class): string
    {
        $shortName = class_basename($class);

        foreach (self::NAME_SUFFIXES as $suffix) {
            if ($shortName !== $suffix && str_ends_with($shortName, $suffix)) {
                return Str::plural(substr($shortName, 0, -strlen($suffix)));
            }
        }

        return Str::plural($shortName);
    }

    /**
     * @param  array<string, true>  $usedNames
     */
    private function numbered(string $name, array $usedNames): string
    {
        $suffix = 2;

        while (isset($usedNames[$this->nameKey($name.$suffix)])) {
            $suffix++;
        }

        return $name.$suffix;
    }

    /**
     * @param  array<string, array<string, float>>  $adjacency
     * @param  list<string>  $members
     */
    private function cohesion(array $adjacency, array $members): float
    {
        $inside = array_flip($members);
        $internal = 0.0;
        $external = 0.0;

        foreach ($members as $member) {
            foreach ($adjacency[$member] as $neighbour => $weight) {
                if (isset($inside[$neighbour])) {
                    $internal += $weight / 2;
                } else {
                    $external += $weight;
                }
            }
        }

        $total = $internal + $external;

        return $total <= 0 ? 0.0 : round($internal / $total, 2);
    }
}
