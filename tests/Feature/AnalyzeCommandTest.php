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

it('requires the json format when writing to a file', function (): void {
    $this->artisan('domain:analyze', ['--output' => 'report.json'])
        ->expectsOutputToContain('The --output option requires --format=json.')
        ->assertFailed();
});

it('reports configuration errors', function (): void {
    config(['domain-migrator.paths' => ['missing']]);

    $this->artisan('domain:analyze')
        ->expectsOutputToContain('The scan path [missing] does not exist.')
        ->assertFailed();
});
