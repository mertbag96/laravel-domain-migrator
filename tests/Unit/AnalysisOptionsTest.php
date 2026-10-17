<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;

it('builds options from the config array', function (): void {
    $options = AnalysisOptions::fromConfig([
        'base_path' => '/srv/app/',
        'paths' => ['app', 'src'],
        'exclude' => ['app/Providers/*'],
        'weights' => ['relationship' => 9],
        'report' => ['hotspots' => 5],
    ], '/default');

    expect($options->basePath)->toBe('/srv/app')
        ->and($options->paths)->toBe(['app', 'src'])
        ->and($options->exclude)->toBe(['app/Providers/*'])
        ->and($options->weights->relationship)->toBe(9)
        ->and($options->weights->usage)->toBe(2)
        ->and($options->hotspotLimit)->toBe(5);
});

it('builds clustering options from the config array', function (): void {
    $options = AnalysisOptions::fromConfig([
        'paths' => ['app'],
        'hints' => ['Billing' => ['\App\Models\Invoice', 'App\Models\Payment']],
        'clustering' => ['resolution' => 2, 'shared_min_domains' => 4],
    ], '/default');

    expect($options->clustering->hints)->toBe(['Billing' => ['App\Models\Invoice', 'App\Models\Payment']])
        ->and($options->clustering->resolution)->toBe(2.0)
        ->and($options->clustering->sharedMinDomains)->toBe(4);
});

it('falls back to the application base path', function (): void {
    expect(AnalysisOptions::fromConfig(['paths' => ['app']], '/default')->basePath)->toBe('/default');
});

it('rejects invalid config values', function (array $config, string $message): void {
    expect(fn () => AnalysisOptions::fromConfig($config, '/default'))
        ->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'paths as string' => [['paths' => 'app'], 'domain-migrator.paths'],
    'empty path' => [['paths' => ['']], 'domain-migrator.paths'],
    'negative weight' => [['weights' => ['usage' => -1]], 'domain-migrator.weights.usage'],
    'zero hotspots' => [['report' => ['hotspots' => 0]], 'domain-migrator.report.hotspots'],
    'base path as array' => [['base_path' => []], 'domain-migrator.base_path'],
    'hints as list' => [['hints' => [['App\Models\Invoice']]], 'domain-migrator.hints'],
    'hint class as array' => [['hints' => ['Billing' => [[]]]], 'domain-migrator.hints.Billing'],
    'zero resolution' => [['clustering' => ['resolution' => 0]], 'domain-migrator.clustering.resolution'],
    'one shared domain' => [['clustering' => ['shared_min_domains' => 1]], 'domain-migrator.clustering.shared_min_domains'],
]);
