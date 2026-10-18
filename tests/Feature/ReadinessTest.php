<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use MertBag\DomainMigrator\Actions\AnalyzeApplicationAction;
use MertBag\DomainMigrator\Actions\AssessReadinessAction;
use MertBag\DomainMigrator\Actions\PlanMigrationAction;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\DomainReadiness;
use MertBag\DomainMigrator\Data\MigrationOptions;
use MertBag\DomainMigrator\Data\ScanResult;
use MertBag\DomainMigrator\Enums\ReadinessStatus;

function readinessMap(): BoundaryMap
{
    return blogMap([
        'Posts' => [
            'App\Models\Post',
            'App\Models\Comment',
            'App\Jobs\PublishPost',
            'App\Notifications\PostPublished',
            'App\Events\PostWasPublished',
            'App\Listeners\NotifyAuthor',
            'App\Console\Commands\PublishScheduledPosts',
            'App\Policies\PostPolicy',
            'App\Livewire\PostList',
        ],
        'Web' => ['App\Http\Controllers\PostController'],
    ]);
}

/**
 * @return list<DomainReadiness>
 */
function assessBlog(): array
{
    $report = app(AnalyzeApplicationAction::class)->execute(blogOptions()->withDomainMap(readinessMap()));

    return app(AssessReadinessAction::class)->execute($report, blogPath(), new MigrationOptions, readinessMap());
}

it('ranks domains from the easiest to move to the hardest', function (): void {
    $readiness = assessBlog();

    expect(array_map(fn (DomainReadiness $entry): string => $entry->plan->domain, $readiness))->toBe(['Web', 'Users', 'Posts'])
        ->and($readiness[0]->status())->toBe(ReadinessStatus::Ready)
        ->and($readiness[1]->status())->toBe(ReadinessStatus::NeedsPreparation)
        ->and($readiness[2]->status())->toBe(ReadinessStatus::NeedsPreparation)
        ->and($readiness[1]->preparationItems())->toBeLessThan($readiness[2]->preparationItems());
});

it('plans every domain from one scan with the same result as planning each domain', function (): void {
    $scan = new ScanResult(app(AnalyzeApplicationAction::class)->execute(blogOptions())->graph, []);
    $plans = app(PlanMigrationAction::class)->executeAll($scan, blogPath(), new MigrationOptions, readinessMap());
    $single = app(PlanMigrationAction::class)->execute(blogOptions(), new MigrationOptions, readinessMap(), 'Posts');
    $posts = $plans[array_search('Posts', array_map(fn ($plan): string => $plan->domain, $plans), true)];

    expect($plans)->toHaveCount(3)
        ->and($posts->toArray())->toBe($single->toArray());
});

it('summarizes the plan of each domain', function (): void {
    $web = assessBlog()[0]->toArray();

    expect($web)->toMatchArray([
        'domain' => 'Web',
        'namespace' => 'App\Domain\Web',
        'status' => 'ready',
        'moves' => 1,
        'blockers' => 0,
        'breaking' => 0,
        'preparation_items' => 0,
    ])->and($web['referencing_files'])->toBeGreaterThan(0);
});

describe('domain:analyze', function (): void {
    beforeEach(function (): void {
        config([
            'domain-migrator.base_path' => blogPath(),
            'domain-migrator.paths' => ['app'],
            'domain-migrator.exclude' => ['app/Providers/*'],
        ]);
    });

    it('suggests where to start in the console', function (): void {
        $this->artisan('domain:analyze')
            ->expectsOutputToContain('Where to start')
            ->expectsOutputToContain('to see what moving it involves.')
            ->assertSuccessful();
    });

    it('adds the readiness of each domain to the json and html reports', function (): void {
        Artisan::call('domain:analyze', ['--format' => 'json']);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($report['readiness'])->toHaveCount($report['summary']['domains'])
            ->and($report['readiness'][0])->toHaveKeys(['domain', 'status', 'moves', 'referencing_files', 'preparation_items', 'risks']);
    });

    it('still analyzes when the move readiness cannot be assessed', function (): void {
        config(['domain-migrator.base_path' => blogCopy(['composer.json' => '{"autoload": "app/"}'])]);

        $this->artisan('domain:analyze')
            ->expectsOutputToContain('Skipped the move readiness of each domain')
            ->expectsOutputToContain('Analyzed')
            ->assertSuccessful();
    });
});
