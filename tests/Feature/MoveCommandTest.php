<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->project = blogCopy();

    config([
        'domain-migrator.base_path' => $this->project,
        'domain-migrator.paths' => ['app'],
        'domain-migrator.exclude' => ['app/Providers/*'],
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
function moveOutput(array $arguments): array
{
    $exitCode = Artisan::call('domain:move', ['--map' => test()->mapPath, ...$arguments]);

    return [$exitCode, (string) preg_replace('/\s+/', ' ', Artisan::output())];
}

it('stops before breaking risks unless it is forced', function (): void {
    [$exitCode, $output] = moveOutput(['domain' => 'Posts']);

    expect($exitCode)->not->toBe(0)
        ->and($output)->toContain('breaks')
        ->and($this->project.'/app/Models/Post.php')->toBeFile()
        ->and($this->project.'/app/Domain')->not->toBeDirectory();
});

it('changes nothing on a dry run', function (): void {
    [$exitCode, $output] = moveOutput(['domain' => 'Posts', '--force' => true, '--dry-run' => true]);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('app/Models/Post.php → app/Domain/Posts/Models/Post.php', 'Dry run: nothing was changed.')
        ->and($this->project.'/app/Models/Post.php')->toBeFile()
        ->and($this->project.'/app/Domain')->not->toBeDirectory();
});

it('refuses an unknown domain', function (): void {
    [$exitCode, $output] = moveOutput(['domain' => 'Nope']);

    expect($exitCode)->not->toBe(0)->and($output)->toContain('not in the domain map');
});

it('moves the files and rewrites their namespaces', function (): void {
    [$exitCode] = moveOutput(['domain' => 'Posts', '--force' => true]);

    $post = (string) file_get_contents($this->project.'/app/Domain/Posts/Models/Post.php');
    $controller = (string) file_get_contents($this->project.'/app/Domain/Posts/Http/Controllers/PostController.php');

    expect($exitCode)->toBe(0)
        ->and($this->project.'/app/Models/Post.php')->not->toBeFile()
        ->and($post)->toContain('namespace App\Domain\Posts\Models;')
        ->and($controller)->toContain('namespace App\Domain\Posts\Http\Controllers;')
        ->and($controller)->toContain('use App\Http\Controllers\Controller;');
});

it('updates imports and class names in the files that refer to the moved classes', function (): void {
    moveOutput(['domain' => 'Posts', '--force' => true]);

    $remaining = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->project, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (preg_match('/\.(php|json)$/', $file->getPathname()) === 1 && ! str_ends_with($file->getPathname(), 'domain-map.json')) {
            preg_match_all('/App\\\\{1,2}(Models|Jobs|Notifications|Events|Listeners|Policies|Livewire|Console\\\\{1,2}Commands|Http\\\\{1,2}Controllers)\\\\{1,2}(Post|Comment|PublishPost|PostPublished|PostWasPublished|NotifyAuthor|PublishScheduledPosts|PostPolicy|PostController|PostList)\b/', (string) file_get_contents($file->getPathname()), $found);
            $remaining = [...$remaining, ...$found[0]];
        }
    }

    expect($remaining)->toBe([])
        ->and((string) file_get_contents($this->mapPath))->toContain('App\\\\Domain\\\\Posts\\\\Models\\\\Post');
});

it('leaves every file parsable and the plan with nothing left to move', function (): void {
    moveOutput(['domain' => 'Posts', '--force' => true]);

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->project.'/app', FilesystemIterator::SKIP_DOTS)) as $file) {
        if (str_ends_with($file->getPathname(), '.php')) {
            expect(token_get_all((string) file_get_contents($file->getPathname()), TOKEN_PARSE))->not->toBeEmpty();
        }
    }

    Artisan::call('domain:plan', ['domain' => 'Posts', '--map' => $this->mapPath, '--format' => 'json']);
    $plan = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($plan['summary']['moves'])->toBe(0)
        ->and($plan['summary']['in_place'])->toBe(10);
});
