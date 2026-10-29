<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->project = blogCopy();

    config([
        'domain-migrator.base_path' => $this->project,
        'domain-migrator.paths' => ['app'],
        'domain-migrator.exclude' => ['app/Providers/*'],
        'domain-migrator.move.backup' => true,
        'domain-migrator.move.backup_path' => '.domain-migrator/backups',
    ]);

    $this->mapPath = $this->project.'/domain-map.json';

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

/**
 * @return array{int, string}
 */
function rollbackOutput(array $arguments = []): array
{
    $exitCode = Artisan::call('domain:rollback', $arguments);

    return [$exitCode, (string) preg_replace('/\s+/', ' ', Artisan::output())];
}

it('does not write a backup on a dry run', function (): void {
    Artisan::call('domain:move', ['domain' => 'Posts', '--map' => $this->mapPath, '--force' => true, '--dry-run' => true]);

    expect($this->project.'/.domain-migrator/backups')->not->toBeDirectory();
});

it('restores the project after a move using the latest backup', function (): void {
    $before = (string) file_get_contents($this->project.'/app/Models/Post.php');

    Artisan::call('domain:move', ['domain' => 'Posts', '--map' => $this->mapPath, '--force' => true]);
    $moveOutput = Artisan::output();

    expect($moveOutput)->toContain('Backup: .domain-migrator/backups/')
        ->and($this->project.'/app/Models/Post.php')->not->toBeFile()
        ->and($this->project.'/app/Domain/Posts/Models/Post.php')->toBeFile();

    [$exitCode] = rollbackOutput();

    expect($exitCode)->toBe(0)
        ->and($this->project.'/app/Models/Post.php')->toBeFile()
        ->and($this->project.'/app/Domain/Posts/Models/Post.php')->not->toBeFile()
        ->and((string) file_get_contents($this->project.'/app/Models/Post.php'))->toBe($before);

    Artisan::call('domain:plan', ['domain' => 'Posts', '--map' => $this->mapPath, '--format' => 'json']);
    $plan = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($plan['summary']['moves'])->toBeGreaterThan(0);
});

it('lists backups after a move', function (): void {
    Artisan::call('domain:move', ['domain' => 'Posts', '--map' => $this->mapPath, '--force' => true]);

    [$exitCode, $output] = rollbackOutput(['--list' => true]);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Posts');
});
