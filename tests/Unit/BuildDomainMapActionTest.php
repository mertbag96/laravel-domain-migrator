<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Actions\BuildDomainMapAction;
use MertBag\DomainMigrator\Analysis\Clustering\DomainDetector;
use MertBag\DomainMigrator\Analysis\Graph\DependencyGraph;
use MertBag\DomainMigrator\Boundaries\BoundaryChecker;
use MertBag\DomainMigrator\Data\AnalysisReport;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\ClusteringOptions;
use MertBag\DomainMigrator\Data\DomainDefinition;

function reportFor(DependencyGraph $graph, ClusteringOptions $options = new ClusteringOptions): AnalysisReport
{
    return new AnalysisReport($graph, [], [], 10, (new DomainDetector)->detect($graph, $options));
}

function shopGraph(array $extra = []): DependencyGraph
{
    return graphOf([
        ...cluster('Order'), ...cluster('Invoice'),
        'Invoice\Actions\CreateInvoice' => ['Order\Models\Order'],
        'Support\Clock' => [],
        ...$extra,
    ]);
}

it('allows the dependencies that exist today in a new map', function (): void {
    $map = (new BuildDomainMapAction(new BoundaryChecker))->execute(reportFor(shopGraph()));

    expect($map->domain('Invoices')?->dependsOn)->toBe(['Orders'])
        ->and($map->domain('Orders')?->dependsOn)->toBe([])
        ->and($map->unassigned)->toBe(['Support\Clock'])
        ->and((new BoundaryChecker)->check(shopGraph(), $map))->toBe([]);
});

it('keeps reviewed domains and rules and only places new classes when updating', function (): void {
    $existing = new BoundaryMap(
        domains: [
            new DomainDefinition('Ordering', ['Order\Models\Order', 'Order\Models\OrderLine', 'Order\Actions\CreateOrder'], ['App\Domain\Ordering'], []),
            new DomainDefinition('Billing', ['Invoice\Models\Invoice', 'Invoice\Models\InvoiceLine', 'Invoice\Actions\CreateInvoice', 'Gone\Class']),
        ],
        shared: ['Support\Clock'],
    );

    $graph = shopGraph(['Order\Actions\CancelOrder' => ['Order\Models\Order', 'Order\Models\OrderLine', 'Support\Clock']]);
    $report = reportFor($graph, (new ClusteringOptions)->withDomainMap($existing));

    $map = (new BuildDomainMapAction(new BoundaryChecker))->execute($report, $existing);
    $ordering = $map->domain('Ordering');

    expect(array_map(fn (DomainDefinition $domain): string => $domain->name, $map->domains))->toBe(['Ordering', 'Billing'])
        ->and($ordering?->classes)->toContain('Order\Actions\CancelOrder')
        ->and($ordering?->namespaces)->toBe(['App\Domain\Ordering'])
        ->and($ordering?->dependsOn)->toBe([])
        ->and($map->domain('Billing')?->dependsOn)->toBeNull()
        ->and($map->domain('Billing')?->classes)->not->toContain('Gone\Class')
        ->and($map->shared)->toBe(['Support\Clock']);
});
