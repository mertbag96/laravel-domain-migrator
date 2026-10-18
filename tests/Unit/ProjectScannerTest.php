<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Data\ClassReference;
use MertBag\DomainMigrator\Data\ProjectScan;
use MertBag\DomainMigrator\Enums\ReferenceKind;
use MertBag\DomainMigrator\Migration\Scanning\ProjectScanner;

const REFERENCE_PATHS = ['app', 'bootstrap', 'config', 'database', 'routes', 'resources/views', 'tests'];

function scanBlog(array $moved, ?string $basePath = null): ProjectScan
{
    return (new ProjectScanner)->scan($basePath ?? blogPath(), REFERENCE_PATHS, $moved);
}

/**
 * @return list<string>
 */
function referenceLines(ProjectScan $scan, ReferenceKind $kind): array
{
    return array_values(array_map(
        fn (ClassReference $reference): string => "{$reference->file}:{$reference->line}",
        array_filter($scan->references, fn (ClassReference $reference): bool => $reference->kind === $kind),
    ));
}

it('finds code references to moved classes outside the application code', function (): void {
    $lines = referenceLines(scanBlog(['App\Models\Post', 'App\Http\Controllers\PostController']), ReferenceKind::Code);

    expect($lines)->toContain(
        'app/Models/User.php:17',
        'config/blog.php:4',
        'routes/web.php:3',
        'routes/web.php:6',
        'tests/Feature/PostTest.php:3',
    )->not->toContain('database/factories/UserFactory.php:5');
});

it('finds class names in strings and Blade templates', function (): void {
    expect(referenceLines(scanBlog(['App\Models\Post']), ReferenceKind::String))
        ->toBe(['config/blog.php:6', 'resources/views/posts/show.blade.php:8']);
});

it('collects what classes declare', function (): void {
    $scan = scanBlog([]);

    expect($scan->facts('App\Models\Post')?->relationCalls)->toBe(['morphMany' => 21])
        ->and($scan->facts('App\Models\Comment')?->relationCalls)->toBe(['morphTo' => 12])
        ->and($scan->facts('App\Notifications\PostPublished')?->channels)->toBe(['database', 'mail'])
        ->and($scan->facts('Database\Factories\UserFactory')?->modelProperty)->toBe('App\Models\User')
        ->and($scan->facts('Database\Factories\PostFactory')?->modelProperty)->toBeNull()
        ->and($scan->traitsOf('App\Models\User'))->toContain('Illuminate\Notifications\Notifiable')
        ->and($scan->interfacesOf('App\Jobs\PublishPost'))->toBe(['Illuminate\Contracts\Queue\ShouldQueue'])
        ->and($scan->ancestors('App\Livewire\PostList'))->toBe(['Livewire\Component']);
});

it('narrows a scan to the references of some classes', function (): void {
    $scan = scanBlog(['App\Models\Post', 'App\Models\User'])->withReferencesTo(['App\Models\User']);

    expect(array_unique(array_map(fn (ClassReference $reference): string => $reference->class, $scan->references)))->toBe(['App\Models\User'])
        ->and($scan->facts('App\Models\Post'))->not->toBeNull();
});

it('finds morph map registrations', function (): void {
    expect(scanBlog([])->morphMap)->toBeNull();

    $directory = blogCopy(['app/Providers/AppServiceProvider.php' => <<<'PHP'
        <?php

        namespace App\Providers;

        use App\Models\Post;
        use Illuminate\Database\Eloquent\Relations\Relation;
        use Illuminate\Support\ServiceProvider;

        class AppServiceProvider extends ServiceProvider
        {
            public function boot(): void
            {
                Relation::enforceMorphMap(['post' => Post::class]);
            }
        }
        PHP]);

    expect(scanBlog([], $directory)->morphMap)->toBe(['App\Models\Post']);
});

it('skips files that cannot be parsed and directories that do not exist', function (): void {
    $directory = blogCopy(['routes/broken.php' => '<?php function (']);

    $scan = (new ProjectScanner)->scan($directory, ['routes', 'missing'], []);

    expect(array_map(fn ($file): string => $file->file, $scan->skippedFiles))->toBe(['routes/broken.php']);
});
