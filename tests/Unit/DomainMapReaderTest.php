<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use MertBag\DomainMigrator\Boundaries\DomainMapReader;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\DomainMap;
use MertBag\DomainMigrator\Exceptions\InvalidDomainMapException;

function readMap(mixed $data): BoundaryMap
{
    return (new DomainMapReader(new Filesystem))->parse($data, 'domain-map.json');
}

it('reads a schema 2 map', function (): void {
    $map = readMap([
        'schema' => 2,
        'domains' => [
            ['name' => 'Billing', 'depends_on' => ['Orders'], 'namespaces' => ['\App\Domain\Billing\\'], 'classes' => ['\App\Models\Invoice']],
            ['name' => 'Orders', 'depends_on' => [], 'classes' => ['App\Models\Order']],
        ],
        'shared' => ['App\Support\Money'],
        'unassigned' => ['App\Support\Clock'],
    ]);

    $billing = $map->domain('Billing');

    expect($billing?->dependsOn)->toBe(['Orders'])
        ->and($billing?->namespaces)->toBe(['App\Domain\Billing'])
        ->and($billing?->classes)->toBe(['App\Models\Invoice'])
        ->and($map->shared)->toBe(['App\Support\Money'])
        ->and($map->unassigned)->toBe(['App\Support\Clock']);
});

it('reads schema 1 maps written by 0.2 without any rules', function (): void {
    $map = readMap([
        'schema' => 1,
        'domains' => [['name' => 'Orders', 'classes' => ['App\Models\Order'], 'cohesion' => 0.5, 'pinned' => false]],
        'shared' => [],
        'unassigned' => [],
    ]);

    expect($map->domain('Orders')?->isChecked())->toBeFalse()
        ->and($map->isOutdated())->toBeTrue();
});

it('rejects invalid maps', function (mixed $data, string $message): void {
    expect(fn () => readMap($data))->toThrow(InvalidDomainMapException::class, $message);
})->with([
    'not an object' => ['nope', 'unsupported schema'],
    'unknown schema' => [['schema' => 3], 'unsupported schema'],
    'domains not a list' => [['schema' => 2, 'domains' => ['a' => []]], '[domains] must be a list of domains'],
    'missing name' => [['schema' => 2, 'domains' => [['classes' => []]]], '[domains.0.name] must be a non-empty string'],
    'classes not strings' => [['schema' => 2, 'domains' => [['name' => 'A', 'classes' => [1]]]], '[domains.0.classes] must be a list of class names'],
    'depends_on not a list' => [['schema' => 2, 'domains' => [['name' => 'A', 'depends_on' => 'B']]], '[domains.0.depends_on] must be a list of domain names'],
    'duplicate domain' => [['schema' => 2, 'domains' => [['name' => 'A'], ['name' => 'A']]], 'defines the domain [A] more than once'],
    'class in two domains' => [['schema' => 2, 'domains' => [['name' => 'A', 'classes' => ['X']], ['name' => 'B', 'classes' => ['X']]]], 'lists [X] more than once'],
    'shared and in a domain' => [['schema' => 2, 'domains' => [['name' => 'A', 'classes' => ['X']]], 'shared' => ['X']], 'lists [X] more than once'],
    'unknown dependency' => [['schema' => 2, 'domains' => [['name' => 'A', 'depends_on' => ['B']]]], '[A] depends on [B], which is not a domain'],
]);

it('reports missing files and invalid json', function (): void {
    $reader = new DomainMapReader(new Filesystem);

    expect(fn () => $reader->read('/missing/domain-map.json'))->toThrow(InvalidDomainMapException::class, 'does not exist');

    $path = sys_get_temp_dir().'/domain-migrator-invalid-map.json';
    file_put_contents($path, '{');

    expect(fn () => $reader->read($path))->toThrow(InvalidDomainMapException::class, 'is not valid JSON');

    unlink($path);
});

it('resolves classes by explicit entry first, then by the longest namespace', function (): void {
    $map = readMap([
        'schema' => 2,
        'domains' => [
            ['name' => 'Billing', 'namespaces' => ['App\Domain\Billing'], 'classes' => ['App\Models\Invoice']],
            ['name' => 'Payments', 'namespaces' => ['App\Domain\Billing\Payments'], 'classes' => ['App\Domain\Billing\Refund']],
        ],
        'shared' => ['App\Support\Money'],
        'unassigned' => ['App\Domain\Billing\Legacy'],
    ]);

    expect($map->domainOf('App\Models\Invoice'))->toBe('Billing')
        ->and($map->domainOf('App\Domain\Billing\Charge'))->toBe('Billing')
        ->and($map->domainOf('App\Domain\Billing\Payments\Gateway'))->toBe('Payments')
        ->and($map->domainOf('App\Domain\Billing\Refund'))->toBe('Payments')
        ->and($map->domainOf('App\Domain\BillingReport'))->toBeNull()
        ->and($map->domainOf('App\Support\Money'))->toBe(DomainMap::SHARED)
        ->and($map->domainOf('App\Domain\Billing\Legacy'))->toBeNull()
        ->and($map->isMapped('App\Domain\Billing\Legacy'))->toBeTrue()
        ->and($map->isMapped('App\Http\Kernel'))->toBeFalse();
});
