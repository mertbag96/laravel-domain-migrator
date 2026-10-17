<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use MertBag\DomainMigrator\Actions\AnalyzeApplicationAction;
use MertBag\DomainMigrator\Data\AnalysisReport;
use MertBag\DomainMigrator\Data\Domain;
use MertBag\DomainMigrator\Data\DomainMap;
use MertBag\DomainMigrator\Reporting\ConsoleReportRenderer;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @param  list<string>  $shared
 */
function renderWithShared(array $shared): string
{
    $analysis = app(AnalyzeApplicationAction::class)->execute(fixtureOptions());

    $report = new AnalysisReport($analysis->graph, $analysis->metrics, [], 10, new DomainMap(
        domains: [new Domain('Orders', ['App\Http\Controllers\OrderController'], 0.5)],
        shared: $shared,
    ));

    $buffer = new BufferedOutput;
    $output = new OutputStyle(new ArrayInput([]), $buffer);

    (new ConsoleReportRenderer)->render($report, $output, new Factory($output));

    return $buffer->fetch();
}

it('lists the most used shared classes first', function (): void {
    $output = renderWithShared(['App\Enums\OrderStatus', 'App\Models\Order']);

    expect($output)->toContain('2 classes')
        ->and(strpos($output, 'Order '))->toBeLessThan(strpos($output, 'OrderStatus'));
});

it('uses fully qualified names when shared classes share a short name', function (): void {
    expect(renderWithShared(['App\Http\Requests\Request', 'App\Api\Request']))
        ->toContain('App\Http\Requests\Request')
        ->toContain('App\Api\Request');
});

it('summarizes the rest of a large shared kernel', function (): void {
    $shared = array_map(fn (string $letter): string => "App\\Support\\Helper{$letter}", range('A', 'J'));

    expect(renderWithShared($shared))->toContain('10 classes')
        ->toContain('HelperH')
        ->not->toContain('HelperI')
        ->toContain('and 2 more');
});
