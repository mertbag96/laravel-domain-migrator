<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    config([
        'domain-migrator.base_path' => blogPath(),
        'domain-migrator.paths' => ['app'],
        'domain-migrator.exclude' => ['app/Providers/*'],
    ]);

    $this->mapPath = sys_get_temp_dir().'/domain-migrator-plan-map-'.bin2hex(random_bytes(4)).'.json';

    file_put_contents($this->mapPath, json_encode([
        'schema' => 2,
        'domains' => array_map(fn ($domain): array => [
            'name' => $domain->name,
            'depends_on' => [],
            'classes' => $domain->classes,
        ], blogMap()->domains),
        'shared' => ['App\Http\Controllers\Controller'],
        'unassigned' => [],
    ]));
});

afterEach(function (): void {
    @unlink($this->mapPath);
});

/**
 * The console output with wrapped lines joined, so long messages can be matched as a whole.
 *
 * @return array{int, string}
 */
function planOutput(array $arguments): array
{
    $exitCode = Artisan::call('domain:plan', ['--map' => test()->mapPath, ...$arguments]);

    return [$exitCode, (string) preg_replace('/\s+/', ' ', Artisan::output())];
}

it('prints the moves, the risks and how to fix them', function (): void {
    [$exitCode, $output] = planOutput(['domain' => 'Posts']);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain(
            'Posts → App\Domain\Posts (10 classes move, 13 files refer to them)',
            'app/Models/Post.php',
            'app/Domain/Posts/Models/Post.php',
            'BREAKING',
            'Polymorphic types',
            'app/Models/Post.php:21',
            'Fix:',
            'REVIEW',
            'config/blog.php:6',
            'Nothing was changed.',
        )
        ->and($output)->not->toContain('BLOCKER');
});

it('writes the plan as JSON', function (): void {
    $exitCode = Artisan::call('domain:plan', ['domain' => 'Posts', '--map' => $this->mapPath, '--format' => 'json']);
    $plan = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($plan['schema'])->toBe(1)
        ->and($plan['summary'])->toBe(['moves' => 10, 'in_place' => 0, 'referencing_files' => 13, 'blockers' => 0, 'breaking' => 8, 'review' => 1])
        ->and($plan['moves'][0])->toHaveKeys(['class', 'target', 'file', 'target_file', 'kind'])
        ->and(array_column($plan['risks'], 'type'))->toContain('morph_type', 'queued_payload', 'string_reference');
});

it('lists the available domains for an unknown domain', function (): void {
    [$exitCode, $output] = planOutput(['domain' => 'Billing']);

    expect($exitCode)->toBe(2)
        ->and($output)->toContain('Billing', 'Posts, Users');
});

it('rejects unsupported formats and missing maps', function (): void {
    expect(planOutput(['domain' => 'Posts', '--format' => 'xml'])[0])->toBe(2);

    @unlink($this->mapPath);

    expect(planOutput(['domain' => 'Posts'])[0])->toBe(2);
});
