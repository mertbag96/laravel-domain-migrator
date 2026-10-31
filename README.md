# Laravel Domain Migrator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mertbag96/laravel-domain-migrator.svg)](https://packagist.org/packages/mertbag96/laravel-domain-migrator)
[![Tests](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/tests.yml/badge.svg)](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/tests.yml)
[![Static analysis](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/static-analysis.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

Move a growing Laravel MVC app toward a modular, domain-oriented structure — safely and incrementally.

Run one command to see how your code clusters into domains, which domain to extract first, and what would break if you moved it today:

```bash
composer require --dev mertbag96/laravel-domain-migrator:^0.6.0
php artisan domain:analyze --format=html
```

> **Status:** pre-1.0. **Analyze**, **boundaries**, and **`domain:plan`** are the stable core today. **`domain:move`** (since 0.5.0) is an **early** executor: run **`domain:plan`**, then **`domain:move --dry-run`**, commit on a branch, and handle breaking risks yourself — the tool does not fix morph maps, queues, or similar runtime breaks. Each move writes a **backup** under `.domain-migrator/backups/`; use **`domain:rollback`** to restore file contents (still run tests and `composer dump-autoload` after). The API may still change between minor versions.

![HTML report for Snipe-IT: app title, domain graph, and move risks for the Assets domain](https://raw.githubusercontent.com/mertbag96/laravel-domain-migrator/main/art/html-report.png)

<sub>The HTML report for [Snipe-IT](https://github.com/grokability/snipe-it) (720 classes, 13 suggested domains). The header shows which app the file belongs to.</sub>

![Where to start: domains ranked by how easy they are to move first](https://raw.githubusercontent.com/mertbag96/laravel-domain-migrator/main/art/where-to-start.png)

### Real-world examples

Run on open source apps (read-only analyze, a few seconds on large codebases):

| App | Scale | Domains | Ready to move first (examples) |
|-----|-------|---------|--------------------------------|
| [Snipe-IT](https://github.com/grokability/snipe-it) | 720 classes | 13 suggested | **ComponentAssignments**, **Categories** (Assets needs preparation) |
| [Koel](https://github.com/koel/koel) | 1,029 classes | 26 suggested | **16** domains ready (e.g. **MediaImageUpdates**, **Scanners**, **HookRegistries**) |

Screenshots above are from Snipe-IT. Koel shows more, smaller domains — useful when comparing clustering on a music app vs. an asset manager.

## What it does

The package works in three phases:

1. **Analyze** (read-only, available now) — parses your app, builds a weighted dependency graph of models, relationships, controllers, jobs, events and services, suggests candidate domains and a shared kernel, ranks **where to start** (move readiness in the console, JSON and HTML reports), and highlights coupling hot spots.
2. **Enforce** (available now) — fails CI when a class starts depending on a domain its own domain is not allowed to use, with a baseline for existing violations. Run it as `domain:check`, as a PHPStan rule or as a Pest arch preset; all three read the same domain map.
3. **Migrate** (planning available now) — `domain:plan` shows where each class of a domain would move in `app/Domain/{Name}`, which files refer to them, and what the move would break: polymorphic types, queued payloads, database notifications, broadcast names, factory, policy and listener discovery, and class names in strings. `domain:move` performs the move: it moves the files, rewrites their namespaces and updates every reference.

It does not try to "do DDD for you". Modeling the business is still your job; this package removes the mechanical work and the risk around it.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

```bash
composer require --dev mertbag96/laravel-domain-migrator
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=domain-migrator-config
```

## Usage

### Analyze your application

```bash
php artisan domain:analyze
```

The analyzer parses every PHP file in the configured paths (default: `app/`) without running any of your code, and prints:

- the number of classes per Laravel role (models, controllers, actions, jobs, ...),
- the suggested domains, with their size and cohesion,
- **where to start:** each domain ranked by move readiness (ready, needs preparation, or blocked), with a dry run of what `domain:plan` would report,
- the shared kernel: classes used by many domains,
- the coupling hot spots: classes with the most dependents, which are the hardest to move into a domain,
- files that could not be parsed.

### Interactive HTML report

```bash
php artisan domain:analyze --format=html
php artisan domain:analyze --format=html --output=storage/domain-report.html
```

This writes a single, self-contained HTML file (default: `domain-report.html`). It needs no internet connection, so you can attach it to a pull request or share it with your team.

The report includes:

- a **domain graph** (bubble layout) and a **class graph** with drill-down; double-click a domain to see its classes,
- **where to start:** domains ranked by extraction readiness, with preparation counts and a copyable `domain:plan` command,
- a **domain dependency matrix** with per-cell class dependency counts,
- a detail panel for domains, classes and matrix cells (cohesion, move targets, risks, depends on / used by),
- coupling hot spots and domain cards,
- light/dark theme, deep links (`#domain=Billing&view=classes`), search and PNG export.

Color by domain or by readiness. On large apps, generating the report takes a few seconds because every domain is planned once to compute readiness.

### JSON report

```bash
php artisan domain:analyze --format=json
php artisan domain:analyze --format=json --output=domain-analysis.json
```

The JSON report contains every class with its role, domain and coupling metrics, every dependency with its link types and weight, the suggested domains, move readiness for each domain (status, risks and counts), and the hot spots.

Every field of the analyze, check and plan JSON is described in the [JSON output reference](docs/json-output.md).

### Domain map

```bash
php artisan domain:analyze --map=domain-map.json
```

Writes the suggested domains to a file you can review, edit and commit. `domain:check` and the upcoming migration commands read this file.

```json
{
    "schema": 2,
    "domains": [
        {
            "name": "Billing",
            "depends_on": ["Orders"],
            "namespaces": ["App\\Domain\\Billing"],
            "classes": ["App\\Models\\Invoice", "App\\Jobs\\GenerateInvoice"]
        }
    ],
    "shared": ["App\\Support\\Money"],
    "unassigned": []
}
```

- `classes` assigns classes to the domain wherever they live today, so the map works before you move any file.
- `namespaces` assigns every class under a namespace, which is handy once a domain lives in `app/Domain/Billing`. A class listed in `classes` wins over a namespace, and the longest matching namespace wins otherwise.
- `depends_on` lists the domains this domain may use. A new map allows exactly the dependencies your code has today, so you can tighten it one entry at a time. Remove the key to stop checking a domain.
- `shared` classes may be used by every domain. `unassigned` classes are not checked.

An existing map is never overwritten unless you pass `--force`. To add classes written since the map was created without losing your changes, run:

```bash
php artisan domain:analyze --update --map=domain-map.json
```

`--update` keeps every domain, rule and assignment in the map, places new (and previously unassigned) classes, and drops classes that no longer exist. Review the diff before committing it: where a new class lands decides which rules apply to it.

Maps written by 0.2 have no `depends_on`, so `domain:check` checks nothing until you run `--update` once, which adds today's dependencies as rules without moving any class.

### Enforce domain boundaries

```bash
php artisan domain:check
```

Checks every dependency against `domain-map.json` and fails when a class uses a domain that its own domain does not list in `depends_on`:

```
  Users → Labels (not in depends_on)
  app/Http/Controllers/Users/BadgeController.php:6  BadgeController uses Label (usage, import)

   ERROR  1 new boundary violation. 4 known violations are in the baseline.
```

Every kind of dependency counts, including a plain `use` import, because each one stops the domain from being moved cleanly.

**Adopting it in an existing app.** Tighten `depends_on` to the rules you want, then record today's violations in a baseline so that only new ones fail:

```bash
php artisan domain:check --generate-baseline
```

The baseline (`domain-baseline.json`) identifies violations by class, not by line, so unrelated edits don't invalidate it. When a baselined violation is fixed, `domain:check` tells you to regenerate the baseline.

| Option | Description |
|---|---|
| `--map=` | The domain map (default: `domain-map.json`, or `boundaries.map` in the config) |
| `--baseline=` | The baseline (default: `domain-baseline.json`, or `boundaries.baseline`) |
| `--generate-baseline` | Write every current violation to the baseline and exit successfully |
| `--format=` | `console` (default), `json`, or `github` for inline pull request annotations |

The command exits with `0` when there are no new violations, `1` when there are, and `2` when the map, baseline or configuration is invalid.

**GitHub Actions:**

```yaml
- name: Check domain boundaries
  run: php artisan domain:check --format=github
```

### PHPStan

If your app uses PHPStan 2.1 or newer, the same boundary violations are reported in your editor and in your existing PHPStan run:

```
 ------ --------------------------------------------------------------------------------------------------
  Line   app/Http/Controllers/Users/BadgeController.php
 ------ --------------------------------------------------------------------------------------------------
  6      App\Http\Controllers\Users\BadgeController (Users) depends on App\Models\Label (Labels), but Users
         does not list Labels in depends_on.
         💡 Add Labels to depends_on in domain-map.json if the dependency is intended.
         🪪 domainMigrator.boundary
```

With [phpstan/extension-installer](https://github.com/phpstan/extension-installer) the rule is registered automatically. Otherwise, include it in `phpstan.neon`:

```neon
includes:
    - vendor/mertbag96/laravel-domain-migrator/extension.neon
```

The rule does nothing until `domain-map.json` exists, and it skips violations in `domain-baseline.json`. Both paths are relative to the directory PHPStan runs from and can be changed:

```neon
parameters:
    domainMigrator:
        map: %currentWorkingDirectory%/domain-map.json
        baseline: %currentWorkingDirectory%/domain-baseline.json # or null to report every violation
```

On PHPStan 2.3 and newer, editing the map or the baseline re-analyses the affected files. On older versions, run `vendor/bin/phpstan clear-result-cache` after editing them.

### Pest

If your app uses Pest 3 or newer, the package registers a `domains` arch preset. Add it to an arch test:

```php
// tests/ArchTest.php

arch()->preset()->domains();
```

It fails on the same violations as `domain:check`, with the same report:

```
  FAILED  Tests\ArchTest > preset → domains
  Orders → Users (not in depends_on)
  app/Actions/PlaceOrderAction.php:7  PlaceOrderAction uses Order (usage, import)

   ERROR  1 new boundary violation. If a dependency is intended, add the domain to depends_on in [domain-map.json]; otherwise remove it.
```

The preset reads the map, the baseline and the scan settings from `config/domain-migrator.php`, so it does not need the application to be booted. It also fails when the map is missing or was written by 0.2 and has no `depends_on` rules yet.

### Plan a domain move

Before moving a domain, see what the move would involve:

```bash
php artisan domain:plan Posts
```

The plan lists where each class of the domain would go, and every risk with the classes it affects, the file and line, and a fix written for your classes. Nothing is changed:

```
  Posts → App\Domain\Posts (10 classes move, 13 files refer to them)

  app/Http/Controllers/PostController.php ............. app/Domain/Posts/Http/Controllers/PostController.php
  app/Jobs/PublishPost.php ........................................... app/Domain/Posts/Jobs/PublishPost.php
  app/Models/Post.php ..................................................... app/Domain/Posts/Models/Post.php
  ...

   BREAKING  Polymorphic types
  These models are stored by class name in polymorphic *_type columns, and the app has no morph map. Existing rows keep the old class name, so their relations stop resolving after the move.
  app/Models/Post.php:21 .............................................. Post declares a morphMany() relation
  Fix: Before moving, give each model an alias in a service provider with Relation::morphMap(['post' => Post::class]) and update the existing *_type values from the class names to the aliases in a migration.

   BREAKING  Model factories
  Laravel finds a model's factory, and a factory's model, from their class names. After the move the names no longer match, so factory() and seeders fail.
  app/Models/Post.php . Post will look for its factory at Database\Factories\Domain\Posts\Models\PostFactory
  database/factories/PostFactory.php:7 .................... PostFactory guesses its model as App\Models\Post
  Fix: Before moving, add #[UseFactory(PostFactory::class)] to Post, and add protected $model = Post::class; to PostFactory.

   REVIEW  Class names in strings
  Class names in strings, Blade templates and data files are not updated by a rename.
  config/blog.php:6 ............................................................ Post is written as a string
  Fix: Replace strings with ::class references where you can, and update the rest by hand after the move.

   WARN  0 blockers, 8 breaking risks and 1 to review. Handle the breaking risks before deploying the move. Nothing was changed.
```

Each class keeps its path below the domain: `App\Http\Controllers\Api\PostController` becomes `App\Domain\Posts\Http\Controllers\Api\PostController`. A namespace segment that repeats the domain name is dropped, so `App\Models\Posts\Post` becomes `App\Domain\Posts\Models\Post`. Targets and file paths follow the PSR-4 roots in `composer.json`.

Risks come in three levels:

| Level | Meaning | Examples |
|---|---|---|
| Blocker | The move cannot be done as planned | a file that declares more than one class, a file at the wrong PSR-4 path, a target that already exists |
| Breaking | The move works, but something stops working until you handle it | polymorphic types without a morph map, queued payloads, database notifications, broadcast names, factory and policy guessing, listener, command, Livewire, Filament and Nova discovery |
| Review | Worth a look after the move | class names in strings, Blade templates and JSON or YAML files |

Risks the app already handles are left out: models in the morph map, notifications with `databaseType()`, events with `broadcastAs()`, models with `#[UseFactory]` or `newFactory()`, and policies and listeners registered in a service provider or `bootstrap/app.php`. Relations and methods declared in your own traits and parent classes count too.

References are searched in `app`, `bootstrap`, `config`, `database`, `routes`, `resources/views` and `tests`. Use `--format=json` for the full plan, including every reference:

```bash
php artisan domain:plan Posts --format=json > plan.json
```

### Move a domain

`domain:move` performs what `domain:plan` shows:

```bash
php artisan domain:move Posts --dry-run   # list what would change, write nothing
php artisan domain:move Posts             # move it
php artisan domain:move Posts --verify    # move it, then check the result
```

It moves each class file to its planned path, rewrites the `namespace` line, updates `use` imports and fully qualified names, updates class names in strings, Blade views and JSON or YAML files, and updates the domain map. A class that was reached through its old namespace without an import gets an import. The move stops when the plan has a blocker; `--force` moves anyway when the plan only has breaking risks. Review the plan first, then run `composer dump-autoload` and your tests.

> **Before you run a real move**
>
> 1. Run **`domain:plan {Domain}`** and fix **blockers**.
> 2. Run **`domain:move {Domain} --dry-run`** and read every path — suggested domains can include classes that belong elsewhere (for example a global `Handler` grouped into a feature domain). Edit **`domain-map.json`** or use hints until the dry run looks right.
> 3. Work on a **git branch**. A **backup** is written by default; undo with **`domain:rollback`** if needed (add **`.domain-migrator/`** to `.gitignore`).
> 4. Treat **`domain:plan` breaking risks** as still yours: morph types, queued payloads, notifications, discovery, and the rest are **not** auto-fixed. Use **`--force`** only when you know how you will handle them before deploy.

Not rewritten, and listed afterwards: grouped imports (`use App\Models\{A, B}`) and class names in comments.

```bash
php artisan domain:rollback --list          # backups from domain:move
php artisan domain:rollback                 # restore the latest backup
php artisan domain:rollback 2026-10-29T120000-posts
php artisan domain:rollback --dry-run       # preview a restore
```

Disable backups with `domain:move --no-backup` or `'move.backup' => false` in config (not recommended).

`--verify` runs after a real move: every file the move wrote must still exist and parse, and planning the domain again must find nothing left to move. When something is off, the command exits with a failure and lists the problems. The move stays in place, so fix it by hand or restore with `domain:rollback`. It does nothing on `--dry-run`.

### AI coding agents (Laravel Boost)

The package ships [Laravel Boost](https://laravel.com/docs/boost) resources, so AI coding agents in your app learn the safe workflow: analyze, plan, dry run, move and roll back, and when to run `domain:check`. If your app uses Boost, run `php artisan boost:install` (or `boost:update`) after installing or upgrading the package. Boost then adds the package guidelines and offers the `domain-migrator` skill.

## How it works

### Dependencies

Each dependency between two of your classes carries the kinds of links found and a weight, taken from the strongest link:

| Link | Example | Default weight |
|---|---|---|
| `relationship` | `$this->hasMany(Order::class)` | 5 |
| `inheritance` | `extends`, `implements`, `use SomeTrait` | 4 |
| `framework_binding` | `#[ObservedBy(...)]`, a listener's `handle(OrderPlaced $event)` | 3 |
| `usage` | type-hints, `new`, static calls, `::class`, `instanceof`, `catch` | 2 |
| `import` | a `use` statement only | 1 |

References to framework and vendor classes are left out, so the graph only shows how your own classes depend on each other.

### Domains

Classes are grouped with the [Louvain community detection algorithm](https://arxiv.org/abs/0803.0476): classes that depend heavily on each other end up in the same domain. The result is deterministic, so running the analyzer twice on the same code gives the same domains.

- **Base types:** inheritance from a class that many classes extend (a base `Controller`, `Request` or model, a common trait) is ignored while grouping, so it does not pull unrelated features together. Hierarchies that live inside one feature namespace, such as `App\Models\Labels\Label` and its subclasses, stay together.
- **Shared kernel:** a class used by at least three other domains (for example a base controller, a helper or a repository) is listed as shared instead of being forced into one domain. Eloquent models always stay in a domain, because they are what domains are built around.
- **Unassigned:** classes without any dependency on other classes are listed separately so you can place them by hand.
- **Names:** each domain is named after a feature namespace nearly all of its classes share (`Labels`), or else its most connected model (`Orders`). Rename domains in the domain map, or pin them with hints.
- **Cohesion:** the share of a domain's dependency weight that stays inside it. Higher is better.

### Configuration

```php
// config/domain-migrator.php

'paths' => ['app'],
'exclude' => ['app/Providers/*', 'app/Http/Kernel.php', 'app/Console/Kernel.php'],

// Pin classes to a named domain. Classes pinned to different domains are never merged.
'hints' => [
    'Billing' => [App\Models\Invoice::class, App\Models\Payment::class],
],

'clustering' => [
    'resolution' => 1.0,        // raise for more, smaller domains
    'shared_min_domains' => 3,  // how many other domains must use a class before it counts as shared
    'base_type_threshold' => 5, // how many subclasses make a class a base type whose inheritance is ignored
],

'weights' => [
    'relationship' => 5,
    'inheritance' => 4,
    'framework_binding' => 3,
    'usage' => 2,
    'import' => 1,
],

// Default files for domain:check and the Pest preset, relative to the application base path.
'boundaries' => [
    'map' => 'domain-map.json',
    'baseline' => 'domain-baseline.json',
],

// Where domain:plan puts each domain, and where it looks for references to moved classes.
'domain' => [
    'namespace' => 'App\\Domain',
    'reference_paths' => ['app', 'bootstrap', 'config', 'database', 'routes', 'resources/views', 'tests'],
],

// Snapshots for domain:rollback (relative to the application base path).
'move' => [
    'backup' => true,
    'backup_path' => '.domain-migrator/backups',
],
```

## Roadmap

| Version | Scope | Status |
|---|---|---|
| 0.1 | Scanner, dependency graph, coupling metrics, console and JSON report | Done |
| 0.2 | Domain clustering, shared kernel detection, domain map and an HTML report | Done |
| 0.3 | Boundary enforcement: `domain:check` with a baseline and GitHub annotations, `--map --update` | Done |
| 0.3.1 | PHPStan rule that reads the same domain map | Done |
| 0.3.2 | Pest arch preset that reads the same domain map | Done |
| 0.4 | `domain:plan`: target names and paths, references, and Laravel-specific risks for moving a domain | Done |
| 0.4.1 | Move readiness in analyze (console, JSON, HTML); redesigned HTML report (domain view, matrix, themes) | Done |
| 0.4.2 | Application name and project path on HTML and JSON reports | Done |
| 0.4.3 | Contributing and security docs; README examples (Snipe-IT, Koel); refreshed screenshots | Done |
| 0.4.4 | Windows: relative paths for analyze and `domain:plan` (fixes false PSR-4 path blockers) | Done |
| 0.5.0 | `domain:move` (early): file moves and reference rewrites; plan gates blockers and breaking risks | Done |
| 0.6.0 | `domain:rollback` and on-disk move backups (default before each move) | Done |
| 0.7–1.0 | Grouped imports and comments, Laravel-aware fixes for plan breaking risks | Next |

## Testing

```bash
composer test
composer analyse
composer format
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

Pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md). To report security issues, see [SECURITY.md](SECURITY.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
