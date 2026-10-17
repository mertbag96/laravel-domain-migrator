<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    config([
        'domain-migrator.base_path' => fixturePath(),
        'domain-migrator.paths' => ['app'],
        'domain-migrator.exclude' => ['app/Providers/*'],
    ]);
});

it('prints a console summary with hot spots and skipped files', function (): void {
    $this->artisan('domain:analyze')
        ->expectsOutputToContain('Analyzed 18 classes')
        ->expectsOutputToContain('Coupling hot spots')
        ->expectsOutputToContain('App\Models\Order')
        ->expectsOutputToContain('Skipped 1 file that could not be parsed')
        ->expectsOutputToContain('app/Support/Broken.php')
        ->assertSuccessful();
});

it('prints the report as json', function (): void {
    Artisan::call('domain:analyze', ['--format' => 'json']);

    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report['schema'])->toBe(1)
        ->and($report['summary']['classes'])->toBe(18)
        ->and($report['summary']['skipped_files'])->toBe(1)
        ->and($report['kinds']['model'])->toBe(5)
        ->and($report['hotspots'][0])->toBe('App\Models\Order')
        ->and($report['dependencies'])->toContain([
            'source' => 'App\Models\Order',
            'target' => 'App\Models\User',
            'types' => ['relationship', 'usage'],
            'weight' => 5,
        ]);
});

it('writes the json report to a file', function (): void {
    $path = sys_get_temp_dir().'/domain-migrator-report.json';

    $this->artisan('domain:analyze', ['--format' => 'json', '--output' => $path])
        ->expectsOutputToContain('Report written to')
        ->assertSuccessful();

    expect(json_decode((string) file_get_contents($path), true)['summary']['classes'])->toBe(18);

    unlink($path);
});

it('rejects unsupported formats', function (): void {
    $this->artisan('domain:analyze', ['--format' => 'xml'])
        ->expectsOutputToContain('Unsupported format')
        ->assertFailed();
});

it('requires a file format when writing to a file', function (): void {
    $this->artisan('domain:analyze', ['--output' => 'report.json'])
        ->expectsOutputToContain('The --output option requires --format=json or --format=html.')
        ->assertFailed();
});

it('lists the suggested domains in the console', function (): void {
    $this->artisan('domain:analyze')
        ->expectsOutputToContain('Suggested domains')
        ->expectsOutputToContain('OrderItems')
        ->doesntExpectOutputToContain('Shared')
        ->assertSuccessful();
});

it('writes a self-contained html report', function (): void {
    $path = sys_get_temp_dir().'/domain-migrator-report.html';

    $this->artisan('domain:analyze', ['--format' => 'html', '--output' => $path])
        ->expectsOutputToContain('Report written to')
        ->assertSuccessful();

    $html = (string) file_get_contents($path);
    preg_match('#<script type="application/json" id="report-data">(.*?)</script>#s', $html, $matches);

    expect($html)->toStartWith('<!DOCTYPE html>')
        ->not->toContain('{{REPORT_JSON}}')
        ->not->toContain('src="http')
        ->and(json_decode($matches[1], true)['summary']['classes'])->toBe(18);

    unlink($path);
});

it('writes the domain map next to the report', function (): void {
    $path = sys_get_temp_dir().'/domain-migrator-map.json';
    @unlink($path);

    $this->artisan('domain:analyze', ['--map' => $path])
        ->expectsOutputToContain('Domain map written to')
        ->assertSuccessful();

    $map = json_decode((string) file_get_contents($path), true);

    $orders = collect($map['domains'])->firstWhere('name', 'Orders');

    expect($map['schema'])->toBe(2)
        ->and($map['shared'])->toBe([])
        ->and(array_column($map['domains'], 'name'))->toContain('OrderItems')
        ->and($orders['depends_on'])->toContain('Users')
        ->and($orders['namespaces'])->toBe([]);

    unlink($path);
});

it('updates a reviewed domain map without losing changes', function (): void {
    $path = sys_get_temp_dir().'/domain-migrator-map.json';
    @unlink($path);

    $this->artisan('domain:analyze', ['--map' => $path])->assertSuccessful();

    $map = json_decode((string) file_get_contents($path), true);

    foreach ($map['domains'] as &$domain) {
        if ($domain['name'] === 'Orders') {
            $domain['name'] = 'Ordering';
            $domain['depends_on'] = [];
        }

        $domain['classes'] = array_values(array_diff($domain['classes'], ['App\Services\InvoiceNumberGenerator']));
        $domain['depends_on'] = array_values(array_map(fn (string $name): string => $name === 'Orders' ? 'Ordering' : $name, $domain['depends_on']));
    }

    file_put_contents($path, json_encode($map));

    $this->artisan('domain:analyze', ['--map' => $path, '--update' => true])
        ->expectsOutputToContain('updated with new classes')
        ->assertSuccessful();

    $updated = json_decode((string) file_get_contents($path), true);
    $ordering = collect($updated['domains'])->firstWhere('name', 'Ordering');
    $placed = collect($updated['domains'])->first(fn (array $domain): bool => in_array('App\Services\InvoiceNumberGenerator', $domain['classes'], true));

    expect($ordering['depends_on'])->toBe([])
        ->and($ordering['classes'])->toContain('App\Http\Controllers\OrderController')
        ->and(array_column($updated['domains'], 'name'))->not->toContain('Orders')
        ->and($placed)->not->toBeNull();

    unlink($path);
});

it('requires a map to update', function (): void {
    $this->artisan('domain:analyze', ['--update' => true])
        ->expectsOutputToContain('The --update option requires --map')
        ->assertFailed();

    $this->artisan('domain:analyze', ['--update' => true, '--map' => sys_get_temp_dir().'/missing-domain-map.json'])
        ->expectsOutputToContain('does not exist')
        ->assertFailed();
});

it('does not overwrite an existing domain map without --force', function (): void {
    $path = sys_get_temp_dir().'/domain-migrator-map.json';
    file_put_contents($path, '{"edited": true}');

    $this->artisan('domain:analyze', ['--map' => $path])
        ->expectsOutputToContain('already exists')
        ->assertFailed();

    expect(file_get_contents($path))->toBe('{"edited": true}');

    $this->artisan('domain:analyze', ['--map' => $path, '--force' => true])->assertSuccessful();

    expect(json_decode((string) file_get_contents($path), true))->toHaveKey('domains');

    unlink($path);
});

it('reports configuration errors', function (): void {
    config(['domain-migrator.paths' => ['missing']]);

    $this->artisan('domain:analyze')
        ->expectsOutputToContain('The scan path [missing] does not exist.')
        ->assertFailed();
});
