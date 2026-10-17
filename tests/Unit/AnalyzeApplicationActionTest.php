<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Actions\AnalyzeApplicationAction;
use MertBag\DomainMigrator\Analysis\Clustering\DomainDetector;
use MertBag\DomainMigrator\Analysis\Graph\GraphBuilder;
use MertBag\DomainMigrator\Analysis\Metrics\MetricsCalculator;
use MertBag\DomainMigrator\Analysis\Scanning\ClassScanner;
use MertBag\DomainMigrator\Analysis\Scanning\SourceFileFinder;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\AnalysisReport;
use MertBag\DomainMigrator\Data\ClusteringOptions;
use MertBag\DomainMigrator\Enums\ClassKind;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;

function analyze(AnalysisOptions $options): AnalysisReport
{
    return (new AnalyzeApplicationAction(
        new SourceFileFinder,
        new ClassScanner,
        new GraphBuilder,
        new MetricsCalculator,
        new DomainDetector,
    ))->execute($options);
}

it('scans every class outside the excluded paths', function (): void {
    $report = analyze(fixtureOptions());

    expect($report->graph->nodes)->toHaveCount(18)
        ->and($report->graph->nodes)->not->toHaveKey('App\Providers\AppServiceProvider');
});

it('reports files that could not be parsed', function (): void {
    $report = analyze(fixtureOptions());

    expect($report->skippedFiles)->toHaveCount(1)
        ->and($report->skippedFiles[0]->file)->toBe('app/Support/Broken.php');
});

it('resolves the Laravel role of each class', function (string $class, ClassKind $kind): void {
    expect(analyze(fixtureOptions())->graph->nodes[$class]->kind)->toBe($kind);
})->with([
    ['App\Models\User', ClassKind::Model],
    ['App\Http\Controllers\OrderController', ClassKind::Controller],
    ['App\Http\Requests\StoreOrderRequest', ClassKind::FormRequest],
    ['App\Http\Resources\OrderResource', ClassKind::Resource],
    ['App\Actions\PlaceOrderAction', ClassKind::Action],
    ['App\Events\OrderPlaced', ClassKind::Event],
    ['App\Listeners\QueueInvoiceGeneration', ClassKind::Listener],
    ['App\Jobs\GenerateInvoice', ClassKind::Job],
    ['App\Services\InvoiceNumberGenerator', ClassKind::Service],
    ['App\Policies\OrderPolicy', ClassKind::Policy],
    ['App\Observers\OrderObserver', ClassKind::Observer],
    ['App\Exceptions\OutOfStockException', ClassKind::Exception],
    ['App\Enums\OrderStatus', ClassKind::Enum],
]);

it('calculates coupling metrics', function (): void {
    $order = analyze(fixtureOptions())->metrics['App\Models\Order'];

    expect($order->afferent)->toBe(8)
        ->and($order->efferent)->toBe(5)
        ->and($order->instability)->toBe(0.38);
});

it('ranks the most depended-on classes as hot spots', function (): void {
    $hotspots = analyze(fixtureOptions())->hotspots();

    expect($hotspots[0]->class)->toBe('App\Models\Order')
        ->and($hotspots[1]->class)->toBe('App\Models\User');
});

it('limits the number of hot spots', function (): void {
    $options = new AnalysisOptions(fixturePath(), ['app'], ['app/Providers/*'], hotspotLimit: 3);

    expect(analyze($options)->hotspots())->toHaveCount(3);
});

it('suggests domains for the application', function (): void {
    $domains = analyze(fixtureOptions())->domains;

    expect($domains->shared)->toBe(['App\Models\Order'])
        ->and($domains->domainOf('App\Models\OrderItem'))->toBe($domains->domainOf('App\Models\Product'))
        ->and($domains->domainOf('App\Jobs\GenerateInvoice'))->toBe($domains->domainOf('App\Listeners\QueueInvoiceGeneration'));
});

it('respects domain hints', function (): void {
    $options = new AnalysisOptions(fixturePath(), ['app'], ['app/Providers/*'], clustering: new ClusteringOptions(hints: [
        'Billing' => ['App\Models\Invoice', 'App\Jobs\GenerateInvoice'],
    ]));

    $domains = analyze($options)->domains;

    expect($domains->domainOf('App\Models\Invoice'))->toBe('Billing')
        ->and($domains->domainOf('App\Jobs\GenerateInvoice'))->toBe('Billing');
});

it('fails when a scan path does not exist', function (): void {
    analyze(new AnalysisOptions(fixturePath(), ['missing']));
})->throws(InvalidConfigurationException::class, 'The scan path [missing] does not exist.');
