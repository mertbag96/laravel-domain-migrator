<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Analysis\Clustering\DomainDetector;
use MertBag\DomainMigrator\Analysis\Graph\DependencyGraph;
use MertBag\DomainMigrator\Analysis\Graph\GraphBuilder;
use MertBag\DomainMigrator\Data\ClusteringOptions;
use MertBag\DomainMigrator\Data\DependencyWeights;
use MertBag\DomainMigrator\Data\DomainMap;
use MertBag\DomainMigrator\Data\ScannedClass;
use MertBag\DomainMigrator\Enums\DeclarationType;
use MertBag\DomainMigrator\Enums\DependencyType;

/**
 * @param  array<string, list<string>>  $dependencies  Class => classes it depends on.
 */
function graphOf(array $dependencies): DependencyGraph
{
    $names = array_unique([...array_keys($dependencies), ...array_merge(...array_values($dependencies))]);

    return (new GraphBuilder)->build(array_values(array_map(fn (string $name): ScannedClass => new ScannedClass(
        name: $name,
        declaration: DeclarationType::Class_,
        file: $name.'.php',
        dependencies: array_fill_keys($dependencies[$name] ?? [], [DependencyType::Relationship]),
    ), $names)), new DependencyWeights);
}

function cluster(string $prefix): array
{
    return [
        "{$prefix}\\Models\\{$prefix}" => ["{$prefix}\\Models\\{$prefix}Line", "{$prefix}\\Actions\\Create{$prefix}"],
        "{$prefix}\\Models\\{$prefix}Line" => ["{$prefix}\\Actions\\Create{$prefix}"],
    ];
}

function detect(DependencyGraph $graph, ClusteringOptions $options = new ClusteringOptions): DomainMap
{
    return (new DomainDetector)->detect($graph, $options);
}

it('suggests one domain per densely connected group', function (): void {
    $map = detect(graphOf([...cluster('Order'), ...cluster('Invoice'), 'Order\Actions\CreateOrder' => ['Invoice\Models\Invoice']]));

    expect($map->domains)->toHaveCount(2)
        ->and($map->domainOf('Order\Models\OrderLine'))->toBe($map->domainOf('Order\Models\Order'))
        ->and($map->domainOf('Invoice\Models\Invoice'))->not->toBe($map->domainOf('Order\Models\Order'));
});

it('names domains after their most connected model', function (): void {
    $map = detect(graphOf([...cluster('Order'), ...cluster('Invoice')]));

    expect(array_map(fn ($domain) => $domain->name, $map->domains))->toEqualCanonicalizing(['Orders', 'Invoices']);
});

it('moves classes used by many other domains into the shared kernel', function (): void {
    $graph = graphOf([
        ...cluster('Order'), ...cluster('Invoice'), ...cluster('Ticket'),
        'Order\Actions\CreateOrder' => ['Identity\Models\User'],
        'Invoice\Actions\CreateInvoice' => ['Identity\Models\User'],
        'Ticket\Actions\CreateTicket' => ['Identity\Models\User'],
    ]);

    $map = detect($graph, new ClusteringOptions(sharedMinDomains: 2));

    expect($map->shared)->toBe(['Identity\Models\User'])
        ->and($map->domainOf('Identity\Models\User'))->toBe(DomainMap::SHARED)
        ->and($map->domains)->toHaveCount(3);
});

it('pins hinted classes to a named domain', function (): void {
    $map = detect(graphOf([...cluster('Order'), ...cluster('Invoice')]), new ClusteringOptions(hints: [
        'Billing' => ['Invoice\Models\Invoice'],
    ]));

    $billing = array_values(array_filter($map->domains, fn ($domain) => $domain->name === 'Billing'))[0];

    expect($billing->pinned)->toBeTrue()
        ->and($billing->classes)->toContain('Invoice\Models\Invoice', 'Invoice\Models\InvoiceLine');
});

it('reports classes without internal dependencies as unassigned', function (): void {
    $map = detect(graphOf([...cluster('Order'), 'App\Support\Money' => []]));

    expect($map->unassigned)->toBe(['App\Support\Money'])
        ->and($map->domainOf('App\Support\Money'))->toBeNull();
});

it('measures cohesion as the share of weight that stays inside a domain', function (): void {
    $map = detect(graphOf([...cluster('Order'), ...cluster('Invoice'), 'Order\Actions\CreateOrder' => ['Invoice\Models\Invoice']]));

    expect($map->domains[0]->cohesion)->toBeGreaterThan(0.5)->toBeLessThan(1.0);
});
