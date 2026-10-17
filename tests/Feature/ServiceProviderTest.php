<?php

declare(strict_types=1);

it('merges the package config', function (): void {
    expect(config('domain-migrator.paths'))->toBe(['app'])
        ->and(config('domain-migrator.domain.namespace'))->toBe('App\\Domain')
        ->and(config('domain-migrator.weights.relationship'))->toBe(5);
});

it('publishes the config file', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'domain-migrator-config'])
        ->assertSuccessful();

    expect(config_path('domain-migrator.php'))->toBeFile();

    unlink(config_path('domain-migrator.php'));
});
