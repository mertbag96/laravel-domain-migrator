# Laravel Domain Migrator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mertbag96/laravel-domain-migrator.svg)](https://packagist.org/packages/mertbag96/laravel-domain-migrator)
[![Tests](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/tests.yml/badge.svg)](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/tests.yml)
[![Static analysis](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/static-analysis.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

Move a growing Laravel MVC app toward a modular, domain-oriented structure — safely and incrementally.

> **Status:** pre-1.0. The analyzer and boundary checks are usable today; migration is on the roadmap, and the API may still change between minor versions.

![HTML report for Snipe-IT: 720 classes grouped into 13 suggested domains and a shared kernel](https://raw.githubusercontent.com/mertbag96/laravel-domain-migrator/main/art/html-report.png)

<sub>The HTML report for [Snipe-IT](https://github.com/grokability/snipe-it), generated in about a second.</sub>

## What it does

The package works in three phases:

1. **Analyze** (read-only, available now) — parses your app, builds a weighted dependency graph of models, relationships, controllers, jobs, events and services, suggests candidate domains and a shared kernel, and highlights coupling hot spots.
2. **Enforce** (available now as `domain:check`) — fails CI when a class starts depending on a domain its own domain is not allowed to use, with a baseline for existing violations. A PHPStan rule and a Pest arch preset are coming next.
3. **Migrate** (planned) — moves a domain's classes into `app/Domain/{Name}` with Rector and handles the Laravel-specific traps: morph maps, serialized queued jobs, factory and policy discovery, and string class references.

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
- the shared kernel: classes used by many domains,
- the coupling hot spots: classes with the most dependents, which are the hardest to move into a domain,
- files that could not be parsed.

### Interactive HTML report

```bash
php artisan domain:analyze --format=html
php artisan domain:analyze --format=html --output=storage/domain-report.html
```

This writes a single, self-contained HTML file (default: `domain-report.html`) with an interactive dependency graph colored by domain. It needs no internet connection, so you can attach it to a pull request or share it with your team. Click a class to see what it depends on and what depends on it, click a domain to focus on it, or search for a class.

### JSON report

```bash
php artisan domain:analyze --format=json
php artisan domain:analyze --format=json --output=domain-analysis.json
```

The JSON report contains every class with its role, domain and coupling metrics, every dependency with its link types and weight, the suggested domains, and the hot spots.

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

`--update` keeps every domain, rule and assignment in the map, places new (and previously unassigned) classes, and drops classes that no longer exist. Review the diff before committing it.

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

// Default files for domain:check, relative to the application base path.
'boundaries' => [
    'map' => 'domain-map.json',
    'baseline' => 'domain-baseline.json',
],
```

## Roadmap

| Version | Scope | Status |
|---|---|---|
| 0.1 | Scanner, dependency graph, coupling metrics, console and JSON report | Done |
| 0.2 | Domain clustering, shared kernel detection, domain map and an HTML report | Done |
| 0.3 | Boundary enforcement: `domain:check` with a baseline and GitHub annotations, `--map --update` | Done |
| 0.3.x | PHPStan rule and Pest arch preset that read the same domain map | Next |
| 0.4–1.0 | `domain:plan` and `domain:move` with Laravel-aware safety handling | Planned |

## Testing

```bash
composer test
composer analyse
composer format
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
