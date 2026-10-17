# Laravel Domain Migrator

[![Tests](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/tests.yml/badge.svg)](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/tests.yml)
[![Static analysis](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/mertbag96/laravel-domain-migrator/actions/workflows/static-analysis.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

Move a growing Laravel MVC app toward a modular, domain-oriented structure — safely and incrementally.

> **Status:** early development. Nothing is released yet; the API will change.

## What it does

The package works in three phases:

1. **Analyze** (read-only) — parses your app, builds a weighted dependency graph of models, relationships, controllers, jobs, events and services, and suggests candidate domains along with coupling hot spots.
2. **Enforce** — keeps domain boundaries intact through a PHPStan rule, a Pest arch preset or an Artisan command, with a baseline for existing violations.
3. **Migrate** — moves a domain's classes into `app/Domain/{Name}` with Rector and handles the Laravel-specific traps: morph maps, serialized queued jobs, factory and policy discovery, and string class references.

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
- the coupling hot spots: classes with the most dependents, which are the hardest to move into a domain,
- files that could not be parsed.

For the full dependency graph, use the JSON format:

```bash
php artisan domain:analyze --format=json
php artisan domain:analyze --format=json --output=domain-analysis.json
```

Each dependency between two of your classes carries the kinds of links found and a weight, taken from the strongest link:

| Link | Example | Default weight |
|---|---|---|
| `relationship` | `$this->hasMany(Order::class)` | 5 |
| `inheritance` | `extends`, `implements`, `use SomeTrait` | 4 |
| `framework_binding` | `#[ObservedBy(...)]`, a listener's `handle(OrderPlaced $event)` | 3 |
| `usage` | type-hints, `new`, static calls, `::class`, `instanceof`, `catch` | 2 |
| `import` | a `use` statement only | 1 |

References to framework and vendor classes are left out, so the graph only shows how your own classes depend on each other. The weights can be changed in the config file.

## Roadmap

| Version | Scope |
|---|---|
| 0.1 | Scanner, dependency graph, coupling metrics, console and JSON report |
| 0.2 | Domain clustering and an HTML report with an interactive graph |
| 0.3 | Boundary enforcement: PHPStan rule, Pest preset, `domain:check`, baseline |
| 0.4–1.0 | `domain:plan` and `domain:move` with Laravel-aware safety handling |

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
