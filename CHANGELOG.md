# Changelog

All notable changes to `laravel-domain-migrator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.0] - 2026-10-17

Boundary enforcement: once the team has reviewed the domain map, CI keeps the boundaries from getting worse.

### Added

- `domain:check` command that fails when a class depends on a domain that its own domain does not list in `depends_on`. It prints each violation with its file and line, and exits with `0` (no new violations), `1` (new violations) or `2` (invalid map, baseline or configuration).
- Baseline for existing violations: `domain:check --generate-baseline` writes `domain-baseline.json`, after which only new violations fail. Fixed baseline entries are reported.
- `--format=json` and `--format=github` for `domain:check`; the GitHub format shows violations inline on pull requests.
- `domain:analyze --update --map=...` places new and unassigned classes in an existing map without changing reviewed domains, rules or assignments.
- `boundaries.map` and `boundaries.baseline` configuration options.
- The scanner records the line of the first reference to each dependency.

### Changed

- Domain maps use schema 2: each domain has `depends_on` (pre-filled with today's domain-to-domain dependencies) and `namespaces`; `cohesion` and `pinned` stay in the analysis report only. Schema 1 maps are still read, and their domains are not checked until `depends_on` is added.

## [0.2.1] - 2026-10-17

Clustering fixes found by running the analyzer on [Koel](https://github.com/koel/koel) and [Snipe-IT](https://github.com/grokability/snipe-it).

### Added

- `clustering.base_type_threshold` option: inheritance from classes with at least this many subclasses is ignored while grouping.
- Repository, query builder, validation rule and cast roles.

### Changed

- Domains are detected in two passes: shared classes are found first and then left out, so they no longer glue domains together.
- Eloquent models always stay in a domain and are never moved to the shared kernel.
- Base classes and traits that many classes extend no longer pull unrelated features into one domain. Hierarchies inside one feature namespace still stay together.
- Domains are named after a feature namespace nearly all of their classes share before falling back to models. Pivot models are not used as names, and names that only differ in plural form (`User` and `Users`) are not suggested twice.
- `app/Http/Kernel.php` and `app/Console/Kernel.php` are excluded by default, like service providers.
- The console lists the shared kernel as a count and its eight most used classes, with full names when short names collide.
- The HTML report generates a distinct color for every domain and spaces domains further apart.

### Fixed

- Classes in a `Models` namespace that do not extend Eloquent (label templates, builders, traits) are no longer reported as models.

## [0.2.0] - 2026-10-17

First public release.

### Added

- `domain:analyze` command with console, JSON (`--format=json`) and HTML (`--format=html`) reports, and `--output` to write them to a file.
- Self-contained HTML report with an interactive dependency graph colored by domain, a domain legend with cohesion, class details, search, and hot spot and skipped file tables. It works offline and loads no external assets.
- Domain suggestions based on deterministic Louvain community detection, with configurable resolution.
- Shared kernel detection for classes used by many domains (`clustering.shared_min_domains`).
- Domain hints to pin classes to named domains (`hints`).
- Domain names derived from the most connected model, and a cohesion score per domain.
- `--map` option to write the suggested domains to a reviewable `domain-map.json`, never overwritten without `--force`.
- Static scanner that collects relationships, inheritance, attribute bindings, listener handlers, type-hints, instantiations and imports for every class, without executing application code.
- Laravel role detection (model, controller, form request, resource, response, job, listener, ...) from base classes, namespaces and class names.
- Weighted dependency graph limited to application classes, with configurable weights.
- Coupling metrics per class (dependents, dependencies, instability) and a ranked list of hot spots.
- Unparsable files are reported instead of aborting the analysis.

[Unreleased]: https://github.com/mertbag96/laravel-domain-migrator/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/mertbag96/laravel-domain-migrator/compare/v0.2.1...v0.3.0
[0.2.1]: https://github.com/mertbag96/laravel-domain-migrator/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/mertbag96/laravel-domain-migrator/releases/tag/v0.2.0
