<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Analysis\Clustering\Louvain;

/**
 * @param  list<array{0: string, 1: string, 2?: float}>  $edges
 * @return array<string, array<string, float>>
 */
function adjacency(array $edges, array $isolated = []): array
{
    $adjacency = array_fill_keys($isolated, []);

    foreach ($edges as $edge) {
        [$a, $b] = $edge;
        $weight = $edge[2] ?? 1.0;
        $adjacency[$a][$b] = $weight;
        $adjacency[$b][$a] = $weight;
    }

    return $adjacency;
}

function twoTriangles(): array
{
    return adjacency([
        ['a1', 'a2', 5.0], ['a2', 'a3', 5.0], ['a1', 'a3', 5.0],
        ['b1', 'b2', 5.0], ['b2', 'b3', 5.0], ['b1', 'b3', 5.0],
        ['a1', 'b1', 1.0],
    ]);
}

it('separates densely connected groups joined by a weak link', function (): void {
    $partition = (new Louvain)->partition(twoTriangles());

    expect($partition['a1'])->toBe($partition['a2'])->toBe($partition['a3'])
        ->and($partition['b1'])->toBe($partition['b2'])->toBe($partition['b3'])
        ->and($partition['a1'])->not->toBe($partition['b1']);
});

it('is deterministic', function (): void {
    $louvain = new Louvain;

    expect($louvain->partition(twoTriangles()))->toBe($louvain->partition(array_reverse(twoTriangles(), true)));
});

it('keeps isolated nodes in their own community', function (): void {
    $partition = (new Louvain)->partition(adjacency([['a', 'b']], ['lonely']));

    expect($partition['a'])->toBe($partition['b'])
        ->and($partition['lonely'])->not->toBe($partition['a']);
});

it('never merges nodes locked under different labels', function (): void {
    $partition = (new Louvain)->partition(adjacency([['a', 'b', 10.0], ['b', 'c', 10.0]]), [
        'a' => 'Billing',
        'c' => 'Shipping',
    ]);

    expect($partition['a'])->not->toBe($partition['c'])
        ->and($partition['b'])->toBeIn([$partition['a'], $partition['c']]);
});

it('groups nodes locked under the same label', function (): void {
    $partition = (new Louvain)->partition(adjacency([], ['a', 'b']), ['a' => 'Billing', 'b' => 'Billing']);

    expect($partition['a'])->toBe($partition['b']);
});

it('produces more communities at a higher resolution', function (): void {
    $chain = adjacency([['a', 'b'], ['b', 'c'], ['c', 'd'], ['d', 'e'], ['e', 'f'], ['f', 'g'], ['g', 'h']]);

    $coarse = count(array_unique((new Louvain(0.5))->partition($chain)));
    $fine = count(array_unique((new Louvain(3.0))->partition($chain)));

    expect($fine)->toBeGreaterThan($coarse);
});

it('handles an empty graph', function (): void {
    expect((new Louvain)->partition([]))->toBe([]);
});
