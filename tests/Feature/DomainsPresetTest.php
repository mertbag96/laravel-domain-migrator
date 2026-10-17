<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use MertBag\DomainMigrator\Exceptions\InvalidDomainMapException;
use MertBag\DomainMigrator\Pest\DomainsPreset;
use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    $this->mapPath = sys_get_temp_dir().'/domain-migrator-preset-map.json';
    $this->baselinePath = sys_get_temp_dir().'/domain-migrator-preset-baseline.json';

    config([
        'domain-migrator.base_path' => fixturePath(),
        'domain-migrator.paths' => ['app'],
        'domain-migrator.exclude' => ['app/Providers/*'],
        'domain-migrator.boundaries.map' => $this->mapPath,
        'domain-migrator.boundaries.baseline' => $this->baselinePath,
    ]);

    Artisan::call('domain:analyze', ['--map' => $this->mapPath, '--force' => true]);
});

afterEach(function (): void {
    foreach ([$this->mapPath, $this->baselinePath] as $path) {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

function removeOrdersDependencyOnUsers(string $mapPath): void
{
    $map = json_decode((string) file_get_contents($mapPath), true);

    foreach ($map['domains'] as &$domain) {
        if ($domain['name'] === 'Orders') {
            $domain['depends_on'] = array_values(array_diff($domain['depends_on'], ['Users']));
        }
    }

    file_put_contents($mapPath, json_encode($map));
}

arch('the generated map is respected')->preset()->domains();

it('fails with the domain:check report when a domain uses one that is not in depends_on', function (): void {
    removeOrdersDependencyOnUsers($this->mapPath);

    expect(fn (): array => (new DomainsPreset)())->toThrow(function (ExpectationFailedException $exception): void {
        expect(preg_replace('/\s+/', ' ', $exception->getMessage()))
            ->toContain('Orders → Users (not in depends_on)')
            ->toContain('app/Actions/PlaceOrderAction.php:7')
            ->toContain('PlaceOrderAction uses Order (usage, import)')
            ->toContain('new boundary violation');
    });
});

it('passes when the violations are in the baseline', function (): void {
    removeOrdersDependencyOnUsers($this->mapPath);
    Artisan::call('domain:check', ['--generate-baseline' => true]);

    expect((new DomainsPreset)())->toHaveCount(2);
});

it('fails when the map was written by an older version and has no rules', function (): void {
    $map = json_decode((string) file_get_contents($this->mapPath), true);
    $map['schema'] = 1;
    $map['domains'] = array_map(fn (array $domain): array => array_diff_key($domain, ['depends_on' => true]), $map['domains']);
    file_put_contents($this->mapPath, json_encode($map));

    expect(fn (): array => (new DomainsPreset)())->toThrow(ExpectationFailedException::class, 'was written by an older version and has no depends_on rules');
});

it('reports a missing domain map', function (): void {
    unlink($this->mapPath);

    expect(fn (): array => (new DomainsPreset)())->toThrow(InvalidDomainMapException::class);
});
