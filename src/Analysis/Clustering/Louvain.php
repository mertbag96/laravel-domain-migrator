<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Analysis\Clustering;

/**
 * Deterministic Louvain community detection on an undirected weighted graph.
 *
 * Locked nodes never leave their starting community, so nodes locked under
 * different labels can never end up in the same community.
 *
 * @see https://arxiv.org/abs/0803.0476
 */
final readonly class Louvain
{
    private const int MAX_PASSES = 100;

    private const float EPSILON = 1e-10;

    public function __construct(
        private float $resolution = 1.0,
    ) {}

    /**
     * @param  array<string, array<string, float>>  $adjacency  Symmetric weights; every node must be a key.
     * @param  array<string, string>  $locked  Locked node => label.
     * @return array<string, int> Node => community index, numbered in order of first appearance.
     */
    public function partition(array $adjacency, array $locked = []): array
    {
        if ($adjacency === []) {
            return [];
        }

        ksort($adjacency);

        $names = array_keys($adjacency);
        $index = array_flip($names);
        $graph = [];

        foreach ($adjacency as $node => $neighbours) {
            $graph[$index[$node]] = [];

            foreach ($neighbours as $neighbour => $weight) {
                if (isset($index[$neighbour]) && $neighbour !== $node) {
                    $graph[$index[$node]][$index[$neighbour]] = $weight;
                }
            }
        }

        [$community, $lockedNodes] = $this->initialCommunities($names, $locked);
        $membership = range(0, count($names) - 1);

        while (true) {
            [$community, $improved] = $this->moveNodes($graph, $community, $lockedNodes);

            $community = $this->renumber($community);

            foreach ($membership as $original => $node) {
                $membership[$original] = $community[$node];
            }

            $communityCount = count(array_unique($community));

            if (! $improved || $communityCount === count($graph)) {
                break;
            }

            [$graph, $lockedNodes] = $this->aggregate($graph, $community, $communityCount, $lockedNodes);
            $community = range(0, count($graph) - 1);
        }

        $result = [];

        foreach ($names as $position => $name) {
            $result[$name] = $membership[$position];
        }

        return $result;
    }

    /**
     * @param  list<string>  $names
     * @param  array<string, string>  $locked
     * @return array{0: list<int>, 1: array<int, true>}
     */
    private function initialCommunities(array $names, array $locked): array
    {
        $community = [];
        $lockedNodes = [];
        $labelCommunity = [];

        foreach ($names as $position => $name) {
            if (isset($locked[$name])) {
                $community[] = $labelCommunity[$locked[$name]] ??= $position;
                $lockedNodes[$position] = true;
            } else {
                $community[] = $position;
            }
        }

        return [$community, $lockedNodes];
    }

    /**
     * @param  array<int, array<int, float>>  $graph
     * @param  list<int>  $community
     * @param  array<int, true>  $lockedNodes
     * @return array{0: list<int>, 1: bool}
     */
    private function moveNodes(array $graph, array $community, array $lockedNodes): array
    {
        $degree = array_map(array_sum(...), $graph);
        $totalDegree = array_sum($degree);

        if ($totalDegree <= 0) {
            return [$community, false];
        }

        $communityDegree = [];

        foreach ($community as $node => $assigned) {
            $communityDegree[$assigned] = ($communityDegree[$assigned] ?? 0) + $degree[$node];
        }

        $improved = false;

        for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
            $moved = false;

            foreach ($community as $node => $current) {
                if (isset($lockedNodes[$node]) || $degree[$node] <= 0) {
                    continue;
                }

                $weightTo = [];

                foreach ($graph[$node] as $neighbour => $weight) {
                    if ($neighbour !== $node) {
                        $weightTo[$community[$neighbour]] = ($weightTo[$community[$neighbour]] ?? 0) + $weight;
                    }
                }

                ksort($weightTo);

                $communityDegree[$current] -= $degree[$node];

                $best = $current;
                $bestGain = $this->gain($weightTo[$current] ?? 0.0, $communityDegree[$current], $degree[$node], $totalDegree);

                foreach ($weightTo as $candidate => $weight) {
                    $gain = $this->gain($weight, $communityDegree[$candidate], $degree[$node], $totalDegree);

                    if ($gain > $bestGain + self::EPSILON) {
                        $best = $candidate;
                        $bestGain = $gain;
                    }
                }

                $communityDegree[$best] += $degree[$node];

                if ($best !== $current) {
                    $community[$node] = $best;
                    $moved = true;
                    $improved = true;
                }
            }

            if (! $moved) {
                break;
            }
        }

        return [$community, $improved];
    }

    private function gain(float $weightToCommunity, float $communityDegree, float $nodeDegree, float $totalDegree): float
    {
        return $weightToCommunity - $this->resolution * $communityDegree * $nodeDegree / $totalDegree;
    }

    /**
     * @param  list<int>  $community
     * @return list<int>
     */
    private function renumber(array $community): array
    {
        $numbers = [];

        return array_map(function (int $assigned) use (&$numbers): int {
            return $numbers[$assigned] ??= count($numbers);
        }, $community);
    }

    /**
     * @param  array<int, array<int, float>>  $graph
     * @param  list<int>  $community
     * @param  array<int, true>  $lockedNodes
     * @return array{0: array<int, array<int, float>>, 1: array<int, true>}
     */
    private function aggregate(array $graph, array $community, int $communityCount, array $lockedNodes): array
    {
        $aggregated = array_fill(0, $communityCount, []);
        $aggregatedLocks = [];

        foreach ($graph as $node => $neighbours) {
            foreach ($neighbours as $neighbour => $weight) {
                $from = $community[$node];
                $to = $community[$neighbour];
                $aggregated[$from][$to] = ($aggregated[$from][$to] ?? 0) + $weight;
            }

            if (isset($lockedNodes[$node])) {
                $aggregatedLocks[$community[$node]] = true;
            }
        }

        return [$aggregated, $aggregatedLocks];
    }
}
