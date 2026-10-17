<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Boundaries\BoundaryChecker;
use MertBag\DomainMigrator\Data\Baseline;
use MertBag\DomainMigrator\Data\BaselineEntry;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\DomainDefinition;

function boundaryMap(?array $billingDependsOn = [], ?array $ordersDependsOn = []): BoundaryMap
{
    return new BoundaryMap(
        domains: [
            new DomainDefinition('Billing', ['Billing\Invoice', 'Billing\InvoiceLine'], dependsOn: $billingDependsOn),
            new DomainDefinition('Orders', ['Orders\Order', 'Orders\OrderLine'], dependsOn: $ordersDependsOn),
        ],
        shared: ['Support\Money'],
        unassigned: ['Support\Clock'],
    );
}

function violationsOf(BoundaryMap $map): array
{
    return (new BoundaryChecker)->check(graphOf([
        'Billing\Invoice' => ['Billing\InvoiceLine', 'Orders\Order', 'Support\Money', 'Support\Clock', 'Legacy\Report'],
        'Orders\Order' => ['Orders\OrderLine', 'Support\Money'],
        'Support\Money' => ['Billing\Invoice'],
    ]), $map);
}

it('reports dependencies on domains that are not in depends_on', function (): void {
    $violations = violationsOf(boundaryMap());

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->source)->toBe('Billing\Invoice')
        ->and($violations[0]->target)->toBe('Orders\Order')
        ->and($violations[0]->sourceDomain)->toBe('Billing')
        ->and($violations[0]->targetDomain)->toBe('Orders')
        ->and($violations[0]->file)->toBe('Billing\Invoice.php');
});

it('allows dependencies listed in depends_on', function (): void {
    expect(violationsOf(boundaryMap(billingDependsOn: ['Orders'])))->toBe([]);
});

it('does not check domains without depends_on', function (): void {
    expect(violationsOf(boundaryMap(billingDependsOn: null)))->toBe([]);
});

it('ignores shared, unassigned and unmapped classes', function (): void {
    $targets = array_map(fn ($violation) => $violation->target, violationsOf(boundaryMap()));

    expect($targets)->not->toContain('Support\Money', 'Support\Clock', 'Legacy\Report');
});

it('lists the dependencies between domains', function (): void {
    $graph = graphOf(['Billing\Invoice' => ['Orders\Order', 'Orders\OrderLine'], 'Orders\Order' => ['Support\Money']]);

    expect((new BoundaryChecker)->dependencies($graph, boundaryMap()))->toBe(['Billing' => ['Orders'], 'Orders' => []]);
});

it('separates baselined violations from new ones', function (): void {
    $violations = violationsOf(boundaryMap());
    $baseline = new Baseline([new BaselineEntry('Billing\Invoice', 'Orders\Order'), new BaselineEntry('Gone\A', 'Gone\B')]);

    expect($baseline->contains($violations[0]))->toBeTrue()
        ->and(array_map(fn (BaselineEntry $entry): string => $entry->key(), $baseline->fixedEntries($violations)))->toBe(['Gone\A -> Gone\B'])
        ->and(Baseline::fromViolations($violations)->toArray())->toBe([
            'schema' => 1,
            'violations' => [['source' => 'Billing\Invoice', 'target' => 'Orders\Order']],
        ]);
});
