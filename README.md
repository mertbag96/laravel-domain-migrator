# Laravel Domain Migrator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mertbag96/laravel-domain-migrator.svg)](https://packagist.org/packages/mertbag96/laravel-domain-migrator)
[![Tests](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/tests.yml/badge.svg)](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/tests.yml)
[![Static analysis](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/static-analysis.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

Move a growing Laravel MVC app toward a modular, domain-oriented structure — safely and incrementally.

> **Status:** pre-1.0. The analyzer is usable today; enforcement and migration are on the roadmap, and the API may still change between minor versions.

## What it does

The package works in three phases:

1. **Analyze** (read-only, available now) — parses your app, builds a weighted dependency graph of models, relationships, controllers, jobs, events and services, suggests candidate domains and a shared kernel, and highlights coupling hot spots.
2. **Enforce** (planned) — keeps domain boundaries intact through a PHPStan rule, a Pest arch preset or an Artisan command, with a baseline for existing violations.
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

Writes the suggested domains to a file you can review, edit and commit. The upcoming migration commands will read this file. An existing map is never overwritten unless you pass `--force`.

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

- **Shared kernel:** a class used by at least three other domains (for example `User`) is listed as shared instead of being forced into one domain.
- **Unassigned:** classes without any dependency on other classes are listed separately so you can place them by hand.
- **Names:** each domain is named after its most connected model, for example `Orders`. Rename domains in the domain map, or pin them with hints.
- **Cohesion:** the share of a domain's dependency weight that stays inside it. Higher is better.

### Configuration

```php
// config/domain-migrator.php

'paths' => ['app'],
'exclude' => ['app/Providers/*'],

// Pin classes to a named domain. Classes pinned to different domains are never merged.
'hints' => [
    'Billing' => [App\Models\Invoice::class, App\Models\Payment::class],
],

'clustering' => [
    'resolution' => 1.0,        // raise for more, smaller domains
    'shared_min_domains' => 3,  // how many other domains must use a class before it counts as shared
],

'weights' => [
    'relationship' => 5,
    'inheritance' => 4,
    'framework_binding' => 3,
    'usage' => 2,
    'import' => 1,
],
```

## Roadmap

| Version | Scope | Status |
|---|---|---|
| 0.1 | Scanner, dependency graph, coupling metrics, console and JSON report | Done |
| 0.2 | Domain clustering, shared kernel detection, domain map and an HTML report | Done |
| 0.3 | Boundary enforcement: PHPStan rule, Pest preset, `domain:check`, baseline | Next |
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
