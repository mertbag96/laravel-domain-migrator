<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Analysis\Graph\GraphBuilder;
use MertBag\DomainMigrator\Data\DependencyWeights;
use MertBag\DomainMigrator\Data\ScannedClass;
use MertBag\DomainMigrator\Enums\DeclarationType;
use MertBag\DomainMigrator\Enums\DependencyType;

it('keeps only dependencies between scanned classes', function (): void {
    $graph = (new GraphBuilder)->build([
        new ScannedClass('App\Models\Order', DeclarationType::Class_, 'Order.php', dependencies: [
            'App\Models\User' => [DependencyType::Relationship],
            'App\Models\Order' => [DependencyType::Usage],
            'Illuminate\Database\Eloquent\Model' => [DependencyType::Inheritance],
        ]),
        new ScannedClass('App\Models\User', DeclarationType::Class_, 'User.php'),
    ], new DependencyWeights);

    expect($graph->edges)->toHaveCount(1)
        ->and($graph->edges[0]->source)->toBe('App\Models\Order')
        ->and($graph->edges[0]->target)->toBe('App\Models\User');
});

it('orders dependency types by weight and uses the strongest as the edge weight', function (): void {
    $graph = (new GraphBuilder)->build([
        new ScannedClass('App\A', DeclarationType::Class_, 'A.php', dependencies: [
            'App\B' => [DependencyType::Import, DependencyType::Usage, DependencyType::Relationship],
        ]),
        new ScannedClass('App\B', DeclarationType::Class_, 'B.php'),
    ], new DependencyWeights(relationship: 7));

    expect($graph->edges[0]->types)->toBe([DependencyType::Relationship, DependencyType::Usage, DependencyType::Import])
        ->and($graph->edges[0]->weight)->toBe(7);
});

it('treats handler parameters of listeners as framework bindings', function (string $listener, DependencyType $expected): void {
    $graph = (new GraphBuilder)->build([
        new ScannedClass($listener, DeclarationType::Class_, 'Handler.php', handlerParameters: ['App\Events\OrderPlaced']),
        new ScannedClass('App\Events\OrderPlaced', DeclarationType::Class_, 'OrderPlaced.php'),
    ], new DependencyWeights);

    expect($graph->edges[0]->types)->toBe([$expected]);
})->with([
    'listener' => ['App\Listeners\SendReceipt', DependencyType::FrameworkBinding],
    'job' => ['App\Jobs\SendReceipt', DependencyType::Usage],
]);

it('exposes dependents and dependencies per class', function (): void {
    $graph = (new GraphBuilder)->build([
        new ScannedClass('App\A', DeclarationType::Class_, 'A.php', dependencies: ['App\C' => [DependencyType::Usage]]),
        new ScannedClass('App\B', DeclarationType::Class_, 'B.php', dependencies: ['App\C' => [DependencyType::Usage]]),
        new ScannedClass('App\C', DeclarationType::Class_, 'C.php'),
    ], new DependencyWeights);

    expect($graph->dependentsOf('App\C'))->toBe(['App\A', 'App\B'])
        ->and($graph->dependenciesOf('App\A'))->toBe(['App\C'])
        ->and($graph->dependenciesOf('App\C'))->toBe([]);
});
