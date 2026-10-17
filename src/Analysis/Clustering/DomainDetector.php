<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Analysis\Clustering;

use Illuminate\Support\Str;
use MertBag\DomainMigrator\Analysis\Graph\DependencyGraph;
use MertBag\DomainMigrator\Data\ClusteringOptions;
use MertBag\DomainMigrator\Data\Domain;
use MertBag\DomainMigrator\Data\DomainMap;
use MertBag\DomainMigrator\Enums\ClassKind;

/**
 * Suggests domains by clustering the dependency graph, then moves classes
 * used by many other domains into the shared kernel instead of forcing
 * them into a single domain.
 */
final readonly class DomainDetector
{
    private const array NAME_SUFFIXES = [
        'Controller', 'Action', 'Service', 'Job', 'Event', 'Listener', 'Policy', 'Observer',
        'Request', 'Resource', 'Response', 'Notification', 'Exception', 'Command', 'Data',
    ];

    public function detect(DependencyGraph $graph, ClusteringOptions $options): DomainMap
    {
        $hints = $this->hintedClasses($graph, $options);
        $fullAdjacency = $this->undirectedAdjacency($graph);
        $partition = (new Louvain($options->resolution))->partition($fullAdjacency, $hints);

        $shared = $this->sharedClasses($graph, $partition, $hints, $options->sharedMinDomains);
        $adjacency = $this->withoutNodes($fullAdjacency, $shared);

        $communities = [];

        foreach (array_diff_key($partition, array_flip($shared)) as $class => $community) {
            $communities[$community][] = $class;
        }

        usort($communities, fn (array $a, array $b): int => [count($b), $a[0]] <=> [count($a), $b[0]]);

        $domains = [];
        $unassigned = [];
        $usedNames = array_fill_keys(array_keys($options->hints), true);

        foreach ($communities as $members) {
            $label = $this->hintLabel($members, $hints);

            if ($label === null && count($members) === 1) {
                $unassigned[] = $members[0];

                continue;
            }

            $domains[] = new Domain(
                name: $label ?? $this->suggestName($graph, $fullAdjacency, $members, $usedNames),
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
     * @return array<string, array<string, float>>
     */
    private function undirectedAdjacency(DependencyGraph $graph): array
    {
        $adjacency = array_fill_keys(array_keys($graph->nodes), []);

        foreach ($graph->edges as $edge) {
            $adjacency[$edge->source][$edge->target] = ($adjacency[$edge->source][$edge->target] ?? 0.0) + $edge->weight;
            $adjacency[$edge->target][$edge->source] = ($adjacency[$edge->target][$edge->source] ?? 0.0) + $edge->weight;
        }

        return $adjacency;
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
            if (isset($hints[$class])) {
                continue;
            }

            $otherCommunities = [];

            foreach ($graph->dependentsOf($class) as $dependent) {
                if ($partition[$dependent] !== $community) {
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
     * Names the domain after, in order of preference: its most connected model,
     * the model it depends on most, or its most connected class. Names already
     * taken by larger domains are skipped.
     *
     * @param  array<string, array<string, float>>  $adjacency
     * @param  list<string>  $members
     * @param  array<string, true>  $usedNames
     */
    private function suggestName(DependencyGraph $graph, array $adjacency, array $members, array &$usedNames): string
    {
        $isModel = fn (string $class): bool => $graph->nodes[$class]->kind === ClassKind::Model;
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

        $candidates = [
            ...$this->rankByWeight(array_filter($members, $isModel), $weights),
            ...$this->rankByWeight(array_filter(array_unique($neighbours), $isModel), $weights),
            ...$this->rankByWeight($members, $weights),
        ];

        $names = array_map($this->nameFor(...), $candidates);
        $name = current(array_filter($names, fn (string $name): bool => ! isset($usedNames[$name]))) ?: $this->numbered($names[0], $usedNames);

        $usedNames[$name] = true;

        return $name;
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

        while (isset($usedNames[$name.$suffix])) {
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
