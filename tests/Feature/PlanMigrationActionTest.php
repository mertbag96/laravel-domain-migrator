<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use MertBag\DomainMigrator\Actions\PlanMigrationAction;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\ClassMove;
use MertBag\DomainMigrator\Data\MigrationOptions;
use MertBag\DomainMigrator\Data\MigrationPlan;
use MertBag\DomainMigrator\Data\MigrationRisk;
use MertBag\DomainMigrator\Data\RiskItem;
use MertBag\DomainMigrator\Enums\RiskLevel;
use MertBag\DomainMigrator\Enums\RiskType;
use MertBag\DomainMigrator\Exceptions\InvalidMigrationPlanException;

function planBlog(?string $basePath = null, ?BoundaryMap $map = null, string $domain = 'Posts'): MigrationPlan
{
    return app(PlanMigrationAction::class)->execute(blogOptions($basePath), new MigrationOptions, $map ?? blogMap(), $domain);
}

/**
 * @return list<MigrationRisk>
 */
function risksOf(MigrationPlan $plan, RiskType $type): array
{
    return array_values(array_filter($plan->risks, fn (MigrationRisk $risk): bool => $risk->type === $type));
}

/**
 * @return list<string>
 */
function riskClasses(MigrationPlan $plan, RiskType $type): array
{
    return array_merge([], ...array_map(
        fn (MigrationRisk $risk): array => array_map(fn (RiskItem $item): string => $item->class, $risk->items),
        risksOf($plan, $type),
    ));
}

function blogProvider(string $boot, string $imports = ''): string
{
    return <<<PHP
        <?php

        namespace App\Providers;

        {$imports}
        use Illuminate\Support\ServiceProvider;

        class AppServiceProvider extends ServiceProvider
        {
            public function boot(): void
            {
                {$boot}
            }
        }
        PHP;
}

it('plans where every class of the domain moves', function (): void {
    $plan = planBlog();

    expect($plan->namespace)->toBe('App\Domain\Posts')
        ->and(array_map(fn (ClassMove $move): string => $move->target, $plan->moves))->toContain(
            'App\Domain\Posts\Models\Post',
            'App\Domain\Posts\Http\Controllers\PostController',
            'App\Domain\Posts\Livewire\PostList',
        )
        ->and($plan->moves)->toHaveCount(10)
        ->and($plan->referencingFiles())->toContain('config/blog.php', 'routes/web.php', 'app/Models/User.php')
        ->and($plan->count(RiskLevel::Blocker))->toBe(0);
});

it('finds the domain case-insensitively and rejects unknown domains', function (): void {
    expect(planBlog(domain: 'posts')->domain)->toBe('Posts');

    planBlog(domain: 'Billing');
})->throws(InvalidMigrationPlanException::class, 'Available domains: Posts, Users');

it('counts classes already in the domain namespace as in place', function (): void {
    $directory = blogCopy(['app/Domain/Posts/Models/Tag.php' => "<?php\n\nnamespace App\\Domain\\Posts\\Models;\n\nclass Tag {}\n"]);

    $plan = planBlog($directory, blogMap(['Posts' => ['App\Models\Post', 'App\Domain\Posts\Models\Tag']]));

    expect($plan->inPlace)->toBe(['App\Domain\Posts\Models\Tag'])
        ->and($plan->moves)->toHaveCount(1);
});

it('sorts risks by level', function (): void {
    $levels = array_map(fn (MigrationRisk $risk): int => $risk->level->order(), planBlog()->risks);

    expect($levels)->toBe(array_values(Arr::sort($levels)));
});

describe('conflicts', function (): void {
    it('blocks classes outside every PSR-4 root', function (): void {
        $directory = blogCopy(['composer.json' => '{"autoload": {"psr-4": {"Database\\\\Factories\\\\": "database/factories/"}}}']);

        expect(riskClasses(planBlog($directory), RiskType::Conflict))->toContain('App\Models\Post');
    });

    it('blocks files at the wrong path, files with two classes and existing targets', function (): void {
        $directory = blogCopy([
            'app/Jobs/PublishPost.php' => null,
            'app/Jobs/Misplaced.php' => "<?php\n\nnamespace App\\Jobs;\n\nclass PublishPost {}\n",
            'app/Models/Comment.php' => "<?php\n\nnamespace App\\Models;\n\nclass Comment {}\n\nclass Reply {}\n",
            'app/Domain/Posts/Models/Post.php' => "<?php\n\nnamespace App\\Domain\\Posts\\Models;\n\nclass Post {}\n",
        ]);

        $titles = array_map(fn (MigrationRisk $risk): string => $risk->title, risksOf(planBlog($directory, blogMap(['Posts' => [
            'App\Models\Post', 'App\Models\Comment', 'App\Models\Reply', 'App\Jobs\PublishPost',
        ]])), RiskType::Conflict));

        expect($titles)->toHaveCount(3);
    });

    it('blocks two classes that would get the same name', function (): void {
        $directory = blogCopy(['app/Models/Posts/Post.php' => "<?php\n\nnamespace App\\Models\\Posts;\n\nclass Post {}\n"]);

        expect(riskClasses(planBlog($directory, blogMap(['Posts' => ['App\Models\Post', 'App\Models\Posts\Post']])), RiskType::Conflict))
            ->toContain('App\Models\Post', 'App\Models\Posts\Post');
    });
});

describe('morph types', function (): void {
    it('flags models whose class name is stored as a morph type', function (): void {
        expect(riskClasses(planBlog(), RiskType::MorphType))->toBe(['App\Models\Post'])
            ->and(riskClasses(planBlog(map: blogMap(['Users' => [], 'Posts' => ['App\Models\User']])), RiskType::MorphType))->toBe(['App\Models\User']);
    });

    it('finds morph relations declared in project traits', function (): void {
        $directory = blogCopy([
            'app/Models/Concerns/Commentable.php' => "<?php\n\nnamespace App\\Models\\Concerns;\n\ntrait Commentable\n{\n    use HasComments;\n}\n",
            'app/Models/Concerns/HasComments.php' => "<?php\n\nnamespace App\\Models\\Concerns;\n\nuse App\\Models\\Comment;\n\ntrait HasComments\n{\n    public function comments()\n    {\n        return \$this->morphMany(Comment::class, 'commentable');\n    }\n}\n",
            'app/Models/User.php' => str_replace('use Notifiable;', "use Notifiable;\n    use \\App\\Models\\Concerns\\Commentable;", (string) file_get_contents(blogPath('app/Models/User.php'))),
        ]);

        $items = risksOf(planBlog($directory, blogMap(['Users' => [], 'Posts' => ['App\Models\User']])), RiskType::MorphType)[0]->items;

        expect(array_map(fn (RiskItem $item): ?string => $item->location(), $items))
            ->toContain('app/Models/Concerns/HasComments.php:11');
    });

    it('skips models in the morph map', function (): void {
        $directory = blogCopy(['app/Providers/AppServiceProvider.php' => blogProvider(
            "Relation::enforceMorphMap(['post' => Post::class]);",
            "use App\\Models\\Post;\nuse Illuminate\\Database\\Eloquent\\Relations\\Relation;",
        )]);

        expect(risksOf(planBlog($directory), RiskType::MorphType))->toBe([]);
    });
});

describe('queued payloads', function (): void {
    it('flags queued classes and the models they carry', function (): void {
        expect(riskClasses(planBlog(), RiskType::QueuedPayload))->toContain(
            'App\Jobs\PublishPost',
            'App\Notifications\PostPublished',
            'App\Events\PostWasPublished',
            'App\Models\Post',
        );
    });

    it('ignores events broadcast right away', function (): void {
        $directory = blogCopy(['app/Events/PostWasPublished.php' => str_replace(
            'ShouldBroadcast',
            'ShouldBroadcastNow',
            (string) file_get_contents(blogPath('app/Events/PostWasPublished.php')),
        )]);

        expect(riskClasses(planBlog($directory), RiskType::QueuedPayload))->not->toContain('App\Events\PostWasPublished')
            ->and(risksOf(planBlog($directory), RiskType::BroadcastName))->toHaveCount(1);
    });
});

describe('notifications and broadcasts', function (): void {
    it('flags database notifications and broadcast events without a fixed name', function (): void {
        expect(riskClasses(planBlog(), RiskType::DatabaseNotification))->toBe(['App\Notifications\PostPublished'])
            ->and(riskClasses(planBlog(), RiskType::BroadcastName))->toBe(['App\Events\PostWasPublished']);
    });

    it('skips classes that define databaseType or broadcastAs', function (): void {
        $notification = (string) file_get_contents(blogPath('app/Notifications/PostPublished.php'));
        $event = (string) file_get_contents(blogPath('app/Events/PostWasPublished.php'));

        $directory = blogCopy([
            'app/Notifications/PostPublished.php' => str_replace('    public function via', "    public function databaseType(): string\n    {\n        return 'post-published';\n    }\n\n    public function via", $notification),
            'app/Events/PostWasPublished.php' => str_replace('    public function broadcastOn', "    public function broadcastAs(): string\n    {\n        return 'post.published';\n    }\n\n    public function broadcastOn", $event),
        ]);

        expect(risksOf(planBlog($directory), RiskType::DatabaseNotification))->toBe([])
            ->and(risksOf(planBlog($directory), RiskType::BroadcastName))->toBe([]);
    });
});

describe('factories', function (): void {
    it('flags models that guess their factory and factories that guess their model', function (): void {
        $items = risksOf(planBlog(), RiskType::Factory)[0]->items;

        expect(array_map(fn (RiskItem $item): string => $item->class, $items))->toBe(['App\Models\Post', 'Database\Factories\PostFactory'])
            ->and($items[0]->detail)->toContain('Database\Factories\Domain\Posts\Models\PostFactory');
    });

    it('skips models that link their factory and factories that name their model', function (): void {
        $model = (string) file_get_contents(blogPath('app/Models/Post.php'));
        $factory = (string) file_get_contents(blogPath('database/factories/PostFactory.php'));

        $directory = blogCopy([
            'app/Models/Post.php' => str_replace('class Post extends', "#[\\Illuminate\\Database\\Eloquent\\Attributes\\UseFactory(\\Database\\Factories\\PostFactory::class)]\nclass Post extends", $model),
            'database/factories/PostFactory.php' => str_replace('class PostFactory extends', "#[\\Illuminate\\Database\\Eloquent\\Factories\\Attributes\\UseModel(\\App\\Models\\Post::class)]\nclass PostFactory extends", $factory),
        ]);

        expect(risksOf(planBlog($directory), RiskType::Factory))->toBe([]);
    });
});

describe('policies', function (): void {
    it('flags a policy that Laravel can no longer guess', function (): void {
        $plan = planBlog(map: blogMap(['Posts' => ['App\Policies\PostPolicy']]));

        $items = risksOf($plan, RiskType::Policy)[0]->items;

        expect(array_map(fn (RiskItem $item): string => $item->class, $items))->toBe(['App\Models\Post'])
            ->and($items[0]->detail)->toContain('App\Domain\Posts\Policies\PostPolicy');
    });

    it('accepts a policy that moves together with its model', function (): void {
        expect(risksOf(planBlog(), RiskType::Policy))->toBe([]);
    });

    it('skips policies registered explicitly', function (): void {
        $directory = blogCopy(['app/Providers/AppServiceProvider.php' => blogProvider(
            'Gate::policy(Post::class, PostPolicy::class);',
            "use App\\Models\\Post;\nuse App\\Policies\\PostPolicy;\nuse Illuminate\\Support\\Facades\\Gate;",
        )]);

        expect(risksOf(planBlog($directory, blogMap(['Posts' => ['App\Policies\PostPolicy']])), RiskType::Policy))->toBe([]);
    });
});

describe('discovery', function (): void {
    it('flags listeners, commands and Livewire components that are found by directory', function (): void {
        expect(riskClasses(planBlog(), RiskType::Discovery))->toBe([
            'App\Listeners\NotifyAuthor',
            'App\Console\Commands\PublishScheduledPosts',
            'App\Livewire\PostList',
        ]);
    });

    it('skips listeners registered explicitly', function (): void {
        $directory = blogCopy(['app/Providers/AppServiceProvider.php' => blogProvider(
            'Event::listen(PostWasPublished::class, NotifyAuthor::class);',
            "use App\\Events\\PostWasPublished;\nuse App\\Listeners\\NotifyAuthor;\nuse Illuminate\\Support\\Facades\\Event;",
        )]);

        expect(riskClasses(planBlog($directory), RiskType::Discovery))->not->toContain('App\Listeners\NotifyAuthor');
    });
});

it('asks to review class names written as strings', function (): void {
    $items = risksOf(planBlog(), RiskType::StringReference)[0]->items;

    expect(array_map(fn (RiskItem $item): ?string => $item->location(), $items))
        ->toBe(['config/blog.php:6', 'resources/views/posts/show.blade.php:8'])
        ->and(array_map(fn (RiskItem $item): string => $item->detail, $items))->toBe(['is written as a string', 'is used in a Blade template'])
        ->and(risksOf(planBlog(), RiskType::StringReference)[0]->level)->toBe(RiskLevel::Review);
});
