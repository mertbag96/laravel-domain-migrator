<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->mapPath = sys_get_temp_dir().'/domain-migrator-phpstan-map.json';
    $this->baselinePath = sys_get_temp_dir().'/domain-migrator-phpstan-baseline.json';

    file_put_contents($this->mapPath, json_encode([
        'schema' => 2,
        'domains' => [
            ['name' => 'Ordering', 'depends_on' => ['Catalog'], 'classes' => ['App\Actions\PlaceOrderAction']],
            ['name' => 'Users', 'depends_on' => [], 'namespaces' => ['App\Users'], 'classes' => ['App\Models\Order', 'App\Models\User']],
            ['name' => 'Catalog', 'classes' => ['App\Models\Product']],
        ],
        'shared' => ['App\Exceptions\OutOfStockException'],
        'unassigned' => [],
    ]));
});

afterEach(function (): void {
    foreach ([$this->mapPath, $this->baselinePath] as $path) {
        if ($path !== null && file_exists($path)) {
            unlink($path);
        }
    }
});

function boundaryError(string $source, string $sourceDomain, string $target, string $targetDomain, int $line): array
{
    return [
        "{$source} ({$sourceDomain}) depends on {$target} ({$targetDomain}), but {$sourceDomain} does not list {$targetDomain} in depends_on.",
        $line,
        "Add {$targetDomain} to depends_on in domain-migrator-phpstan-map.json if the dependency is intended.",
    ];
}

it('reports dependencies on domains that are not in depends_on', function (): void {
    $this->analyse([fixturePath('app/Actions/PlaceOrderAction.php')], [
        boundaryError('App\Actions\PlaceOrderAction', 'Ordering', 'App\Models\Order', 'Users', 7),
        boundaryError('App\Actions\PlaceOrderAction', 'Ordering', 'App\Models\User', 'Users', 9),
    ]);
});

it('skips violations recorded in the baseline', function (): void {
    file_put_contents($this->baselinePath, json_encode(['schema' => 1, 'violations' => [
        ['source' => 'App\Actions\PlaceOrderAction', 'target' => 'App\Models\Order'],
    ]]));

    $this->analyse([fixturePath('app/Actions/PlaceOrderAction.php')], [
        boundaryError('App\Actions\PlaceOrderAction', 'Ordering', 'App\Models\User', 'Users', 9),
    ]);
});

it('checks traits and namespace-based domains', function (): void {
    $file = temporaryPhpFile(<<<'PHP'
        <?php

        namespace App\Users;

        trait HasOrders
        {
            public function placeOrder(): \App\Actions\PlaceOrderAction
            {
                return new \App\Actions\PlaceOrderAction;
            }
        }
        PHP);

    $this->analyse([$file->absolutePath], [
        boundaryError('App\Users\HasOrders', 'Users', 'App\Actions\PlaceOrderAction', 'Ordering', 7),
    ]);
});

it('does nothing without a domain map', function (): void {
    $this->mapPath = sys_get_temp_dir().'/missing-domain-map.json';

    $this->analyse([fixturePath('app/Actions/PlaceOrderAction.php')], []);
});

it('is registered by extension.neon', function (): void {
    $config = sys_get_temp_dir().'/domain-migrator-phpstan.neon';
    file_put_contents($config, implode(PHP_EOL, [
        'includes:',
        '    - '.dirname(__DIR__, 2).'/extension.neon',
        'parameters:',
        '    level: 0',
        '    tmpDir: '.sys_get_temp_dir().'/domain-migrator-phpstan',
        '    scanDirectories:',
        '        - '.fixturePath('app'),
        '    domainMigrator:',
        "        map: {$this->mapPath}",
        '        baseline: null',
    ]));

    $process = new Process([
        PHP_BINARY, dirname(__DIR__, 2).'/vendor/bin/phpstan', 'analyse', '--no-progress', '--error-format=json', '--memory-limit=1G',
        '-c', $config, fixturePath('app/Actions/PlaceOrderAction.php'),
    ]);
    $process->run();
    unlink($config);

    $files = json_decode($process->getOutput(), true)['files'] ?? [];
    $files = array_combine(array_map(fn (string $path): string => str_replace(chr(92), '/', $path), array_keys($files)), $files);
    $errors = $files[str_replace(chr(92), '/', fixturePath('app/Actions/PlaceOrderAction.php'))]['messages'] ?? [];
    $boundaryErrors = array_values(array_filter($errors, fn (array $error): bool => ($error['identifier'] ?? null) === 'domainMigrator.boundary'));

    expect(array_column($boundaryErrors, 'line'))->toBe([7, 9]);
});
