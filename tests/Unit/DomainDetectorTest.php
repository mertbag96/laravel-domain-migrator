<?php

declare(strict_types=1);

use Illuminate\Support\Str;
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
 * Classes in a Models namespace extend Eloquent; any other class extends the parent given in $parents.
 *
 * @param  array<string, list<string>>  $dependencies  Class => classes it depends on.
 * @param  array<string, string>  $parents  Class => internal parent class.
 */
function graphOf(array $dependencies, array $parents = []): DependencyGraph
{
    $names = array_unique([...array_keys($dependencies), ...array_merge([], ...array_values($dependencies)), ...array_keys($parents), ...array_values($parents)]);

    return (new GraphBuilder)->build(array_values(array_map(function (string $name) use ($dependencies, $parents): ScannedClass {
        $parent = $parents[$name] ?? (str_contains($name, '\\Models\\') ? 'Illuminate\Database\Eloquent\Model' : null);
        $types = array_fill_keys($dependencies[$name] ?? [], [DependencyType::Relationship]);

        if (isset($parents[$name])) {
            $types[$parents[$name]] = [DependencyType::Inheritance];
        }

        return new ScannedClass(
            name: $name,
            declaration: DeclarationType::Class_,
            file: $name.'.php',
            parent: $parent,
            dependencies: $types,
        );
    }, $names)), new DependencyWeights);
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
        'Order\Actions\CreateOrder' => ['Support\Services\Clock'],
        'Invoice\Actions\CreateInvoice' => ['Support\Services\Clock'],
        'Ticket\Actions\CreateTicket' => ['Support\Services\Clock'],
    ]);

    $map = detect($graph, new ClusteringOptions(sharedMinDomains: 2));

    expect($map->shared)->toBe(['Support\Services\Clock'])
        ->and($map->domainOf('Support\Services\Clock'))->toBe(DomainMap::SHARED)
        ->and($map->domains)->toHaveCount(3);
});

it('never moves models into the shared kernel', function (): void {
    $graph = graphOf([
        ...cluster('Order'), ...cluster('Invoice'), ...cluster('Ticket'),
        'Order\Actions\CreateOrder' => ['Identity\Models\User'],
        'Invoice\Actions\CreateInvoice' => ['Identity\Models\User'],
        'Ticket\Actions\CreateTicket' => ['Identity\Models\User'],
    ]);

    $map = detect($graph, new ClusteringOptions(sharedMinDomains: 2));

    expect($map->shared)->toBe([])
        ->and($map->domainOf('Identity\Models\User'))->not->toBeNull();
});

it('ignores inheritance from base types that many classes extend', function (): void {
    $controllers = ['Order\Http\OrderController', 'Order\Http\OrderLineController', 'Invoice\Http\InvoiceController', 'Invoice\Http\InvoiceLineController'];

    $graph = graphOf([
        ...cluster('Order'), ...cluster('Invoice'),
        'Order\Http\OrderController' => ['Order\Models\Order'],
        'Order\Http\OrderLineController' => ['Order\Models\OrderLine'],
        'Invoice\Http\InvoiceController' => ['Invoice\Models\Invoice'],
        'Invoice\Http\InvoiceLineController' => ['Invoice\Models\InvoiceLine'],
    ], array_fill_keys($controllers, 'App\Http\Controllers\Controller'));

    $map = detect($graph, new ClusteringOptions(baseTypeThreshold: 4));

    expect($map->domainOf('Order\Http\OrderController'))->toBe($map->domainOf('Order\Models\Order'))
        ->and($map->domainOf('Invoice\Http\InvoiceController'))->toBe($map->domainOf('Invoice\Models\Invoice'))
        ->and($map->domainOf('Order\Models\Order'))->not->toBe($map->domainOf('Invoice\Models\Invoice'));
});

it('keeps hierarchies that live inside one feature namespace together', function (): void {
    $labels = ['App\Labels\Sheets\Avery', 'App\Labels\Sheets\Dymo', 'App\Labels\Tapes\Brother', 'App\Labels\Tapes\Zebra'];

    $map = detect(graphOf([], array_fill_keys($labels, 'App\Labels\Label')), new ClusteringOptions(baseTypeThreshold: 4));

    expect($map->domains)->toHaveCount(1)
        ->and($map->domains[0]->name)->toBe('Labels')
        ->and($map->domains[0]->classes)->toHaveCount(5);
});

it('names a domain after the namespace nearly all of its classes share', function (): void {
    $map = detect(graphOf([
        'App\Billing\Gateway' => ['App\Billing\Charge', 'App\Billing\Refund'],
        'App\Billing\Charge' => ['App\Billing\Refund'],
    ]));

    expect($map->domains[0]->name)->toBe('Billing');
});

it('does not suggest names that only differ in plural form', function (): void {
    $map = detect(graphOf([
        ...cluster('User'),
        'App\Values\User\Theme' => ['App\Values\User\Locale', 'App\Values\User\Avatar'],
        'App\Values\User\Locale' => ['App\Values\User\Avatar'],
    ]));

    $names = array_map(fn ($domain) => $domain->name, $map->domains);

    expect($names)->toHaveCount(2)
        ->and(array_unique(array_map(fn (string $name): string => Str::plural($name), $names)))->toHaveCount(2);
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
