<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Pest\DomainsPreset;
use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    $this->project = sys_get_temp_dir().'/domain-migrator-preset-project';
    @mkdir($this->project.'/config', recursive: true);

    file_put_contents($this->project.'/config/domain-migrator.php', '<?php return '.var_export([
        'base_path' => fixturePath(),
        'exclude' => ['app/Providers/*'],
    ], true).';');

    file_put_contents($this->project.'/domain-map.json', json_encode([
        'schema' => 2,
        'domains' => [
            ['name' => 'Ordering', 'depends_on' => [], 'classes' => ['App\Actions\PlaceOrderAction']],
        ],
        'shared' => [],
        'unassigned' => [],
    ]));
});

afterEach(function (): void {
    foreach (['config/domain-migrator.php', 'domain-map.json'] as $file) {
        unlink("{$this->project}/{$file}");
    }

    rmdir("{$this->project}/config");
    rmdir($this->project);
});

it('reads the configuration and the map from the project when the application is not booted', function (): void {
    expect((new DomainsPreset($this->project))())->toHaveCount(2);
});

it('checks the classes in the configured paths', function (): void {
    file_put_contents($this->project.'/domain-map.json', json_encode([
        'schema' => 2,
        'domains' => [
            ['name' => 'Ordering', 'depends_on' => [], 'classes' => ['App\Actions\PlaceOrderAction']],
            ['name' => 'Users', 'classes' => ['App\Models\User']],
        ],
        'shared' => [],
        'unassigned' => [],
    ]));

    expect(fn (): array => (new DomainsPreset($this->project))())
        ->toThrow(ExpectationFailedException::class, 'Ordering → Users');
});
