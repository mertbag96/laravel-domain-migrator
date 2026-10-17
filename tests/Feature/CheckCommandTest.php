<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;

beforeEach(function (): void {
    config([
        'domain-migrator.base_path' => fixturePath(),
        'domain-migrator.paths' => ['app'],
        'domain-migrator.exclude' => ['app/Providers/*'],
    ]);

    $this->mapPath = sys_get_temp_dir().'/domain-migrator-check-map.json';
    $this->baselinePath = sys_get_temp_dir().'/domain-migrator-check-baseline.json';
    @unlink($this->mapPath);
    @unlink($this->baselinePath);

    Artisan::call('domain:analyze', ['--map' => $this->mapPath]);
});

afterEach(function (): void {
    @unlink($this->mapPath);
    @unlink($this->baselinePath);
});

/**
 * @param  callable(array<string, mixed>): array<string, mixed>  $edit
 */
function editMap(string $path, callable $edit): void
{
    file_put_contents($path, json_encode($edit(json_decode((string) file_get_contents($path), true))));
}

function withoutDependency(string $path, string $domain, string $dependency): void
{
    editMap($path, function (array $map) use ($domain, $dependency): array {
        foreach ($map['domains'] as &$definition) {
            if ($definition['name'] === $domain) {
                $definition['depends_on'] = array_values(array_diff($definition['depends_on'], [$dependency]));
            }
        }

        return $map;
    });
}

function check(array $options = []): PendingCommand
{
    return test()->artisan('domain:check', ['--map' => test()->mapPath, '--baseline' => test()->baselinePath, ...$options]);
}

/**
 * The console output with wrapped lines joined, so long messages can be matched as a whole.
 *
 * @return array{int, string}
 */
function checkOutput(array $options = []): array
{
    $exitCode = Artisan::call('domain:check', ['--map' => test()->mapPath, '--baseline' => test()->baselinePath, ...$options]);

    return [$exitCode, (string) preg_replace('/\s+/', ' ', Artisan::output())];
}

it('passes right after the map is generated', function (): void {
    check()
        ->expectsOutputToContain('No new boundary violations in 18 classes.')
        ->assertSuccessful();
});

it('fails on dependencies to domains that are not in depends_on', function (): void {
    withoutDependency($this->mapPath, 'Orders', 'Users');

    check()
        ->expectsOutputToContain('Orders → Users')
        ->expectsOutputToContain('app/Actions/PlaceOrderAction.php:7')
        ->expectsOutputToContain('new boundary violations')
        ->assertExitCode(1);
});

it('accepts violations recorded in the baseline', function (): void {
    withoutDependency($this->mapPath, 'Orders', 'Users');

    check(['--generate-baseline' => true])
        ->expectsOutputToContain('Baseline written to')
        ->assertSuccessful();

    $baseline = json_decode((string) file_get_contents($this->baselinePath), true);

    expect($baseline['schema'])->toBe(1)
        ->and($baseline['violations'])->toContain(['source' => 'App\Actions\PlaceOrderAction', 'target' => 'App\Models\Order']);

    [$exitCode, $output] = checkOutput();

    expect($exitCode)->toBe(0)
        ->and($output)->toMatch('/No new boundary violations in 18 classes\. \d+ known violations are in the baseline\./');
});

it('reports baseline entries that no longer occur', function (): void {
    file_put_contents($this->baselinePath, json_encode(['schema' => 1, 'violations' => [['source' => 'App\Gone', 'target' => 'App\Models\Order']]]));

    check()
        ->expectsOutputToContain('1 baseline entry no longer occurs')
        ->assertSuccessful();
});

it('prints the result as json', function (): void {
    withoutDependency($this->mapPath, 'Orders', 'Users');

    Artisan::call('domain:check', ['--map' => $this->mapPath, '--baseline' => $this->baselinePath, '--format' => 'json']);

    $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $violation = collect($result['violations'])->firstWhere('source', 'App\Actions\PlaceOrderAction');

    expect($result['passed'])->toBeFalse()
        ->and($result['summary']['classes'])->toBe(18)
        ->and($violation['source_domain'])->toBe('Orders')
        ->and($violation['target_domain'])->toBe('Users')
        ->and($violation['file'])->toBe('app/Actions/PlaceOrderAction.php')
        ->and($violation['line'])->toBe(7);
});

it('prints github annotations', function (): void {
    withoutDependency($this->mapPath, 'Orders', 'Users');

    Artisan::call('domain:check', ['--map' => $this->mapPath, '--baseline' => $this->baselinePath, '--format' => 'github']);

    expect(Artisan::output())->toMatch('/^::error file=app\/[^,]+\.php,line=\d+,title=Domain boundary%3A Orders → Users::/m');
});

it('does not check domains without depends_on', function (): void {
    editMap($this->mapPath, function (array $map): array {
        foreach ($map['domains'] as &$domain) {
            unset($domain['depends_on']);
        }

        return $map;
    });

    check()
        ->expectsOutputToContain('Not checked because they have no depends_on: Orders')
        ->assertSuccessful();
});

it('explains how to upgrade a map from before schema 2', function (): void {
    editMap($this->mapPath, function (array $map): array {
        $map['schema'] = 1;

        foreach ($map['domains'] as &$domain) {
            unset($domain['depends_on']);
        }

        return $map;
    });

    [$exitCode, $output] = checkOutput();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('was written by an older version and has no depends_on rules, so nothing was checked')
        ->not->toContain('Not checked because');

    $this->artisan('domain:analyze', ['--update' => true, '--map' => $this->mapPath])->assertSuccessful();

    withoutDependency($this->mapPath, 'Orders', 'Users');

    check()->assertExitCode(1);
});

it('warns about classes that are not in the map', function (): void {
    editMap($this->mapPath, function (array $map): array {
        $map['domains'][0]['classes'] = array_slice($map['domains'][0]['classes'], 1);

        return $map;
    });

    [$exitCode, $output] = checkOutput();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('1 class is not in the domain map and is not checked. Run php artisan domain:analyze --update --map=');
});

it('fails with exit code 2 for a missing or invalid map', function (): void {
    unlink($this->mapPath);

    check()->expectsOutputToContain('does not exist')->assertExitCode(2);

    file_put_contents($this->mapPath, json_encode(['schema' => 2, 'domains' => [['name' => 'Orders', 'depends_on' => ['Billing']]]]));

    check()->expectsOutputToContain('[Orders] depends on [Billing], which is not a domain in the map')->assertExitCode(2);
});

it('rejects unsupported formats', function (): void {
    check(['--format' => 'xml'])->expectsOutputToContain('Unsupported format')->assertExitCode(2);
});
