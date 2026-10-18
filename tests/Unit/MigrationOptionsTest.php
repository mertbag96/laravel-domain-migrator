<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Data\MigrationOptions;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;

it('reads the domain namespace and reference paths', function (): void {
    $options = MigrationOptions::fromConfig(['domain' => ['namespace' => '\\Modules\\', 'reference_paths' => ['app', 'tests']]]);

    expect($options->namespace)->toBe('Modules')
        ->and($options->referencePaths)->toBe(['app', 'tests']);
});

it('defaults to App\Domain and the usual Laravel directories', function (): void {
    $options = MigrationOptions::fromConfig([]);

    expect($options->namespace)->toBe('App\Domain')
        ->and($options->referencePaths)->toContain('app', 'config', 'routes', 'resources/views', 'tests');
});

it('rejects invalid values', function (array $domain, string $message): void {
    expect(fn (): MigrationOptions => MigrationOptions::fromConfig(['domain' => $domain]))
        ->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'a namespace with a dash' => [['namespace' => 'App\Domain-Modules'], 'domain.namespace'],
    'paths that are not a list' => [['reference_paths' => 'app'], 'domain.reference_paths'],
    'an empty path' => [['reference_paths' => ['']], 'domain.reference_paths'],
]);
