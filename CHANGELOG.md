# Changelog

All notable changes to `laravel-domain-migrator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Absolute Windows paths (`C:\...`) in `boundaries.map`, `boundaries.baseline`, `paths` and `domain.reference_paths` were treated as relative to the project, so the domain map was reported as missing. They are now recognized as absolute.

### Added

- [Laravel Boost](https://laravel.com/docs/boost) resources: package guidelines and a `domain-migrator` skill that teach AI coding agents the safe analyze, check, plan, move and rollback workflow. Run `php artisan boost:install` or `boost:update` in your app to pick them up.
- [JSON output reference](docs/json-output.md) for `domain:analyze`, `domain:check` and `domain:plan`.

## [0.6.0] - 2026-10-29

Safer **`domain:move`** with on-disk backups and **`domain:rollback`** ([#11](https://github.com/mertbag96/laravel-domain-migrator/pull/11)).

### Added

- **`domain:rollback`**: restores file contents from a **`domain:move`** backup (latest or by id, `--list`, `--dry-run`).
- Move backups written by default to **`.domain-migrator/backups/{id}/`** before each real move; **`domain:move --no-backup`** and **`move.backup`** config to opt out.
- Automatic restore from the snapshot if a move fails while writing files.

### Changed

- **`domain:move`** prints the backup path and a rollback command after a successful move.
- README: backups, rollback commands, and `.domain-migrator/` gitignore note.

## [0.5.0] - 2026-10-29

First **`domain:move`** release: an early executor for what `domain:plan` previews ([#8](https://github.com/mertbag96/laravel-domain-migrator/pull/8), thanks [@mhmadahmd](https://github.com/mhmadahmd)).

### Added

- `domain:move {Domain}`: moves a domain's classes to `app/Domain/{Name}` and rewrites namespaces, imports, fully qualified names, class names in strings, views and data files, then updates the domain map.
- `--dry-run` lists every file that would move or change without writing anything.
- `--force` moves even when the plan has breaking risks; blockers always stop the move.
- `--map` selects the domain map (default: `boundaries.map`, then `domain-map.json`).
- `PlanMigrationAction::executeWithScan()` for callers that already have a project scan (used by `domain:move`).

### Known limitations (0.5.0)

Treat **`domain:move` as early**. It is useful with **`domain:plan`**, git, and **`--dry-run`**, but it is not a full “safe production migration” yet.

- **Breaking risks stay yours.** The command does not fix polymorphic types, queued job payloads, database notifications, broadcast names, factory or policy discovery, and similar issues that `domain:plan` reports. Use `--force` only when you have a plan to handle those risks before deploy.
- **No rollback.** There is no built-in snapshot or undo. Commit (or branch) before you move; review the diff like any large refactor.
- **Domain map quality matters.** Suggested domains come from clustering. A domain can list classes that should not move together (for example app-wide exception handlers). Always read **`--dry-run`** output and trim or adjust the map before a real run.
- **Manual follow-up.** Grouped imports and class names in comments are reported for hand editing; not every reference shape is rewritten.

## [0.4.4] - 2026-10-29

Windows path handling for migration planning and analysis.

### Fixed

- Windows: `relativePath()` now normalizes backslashes, so scanned file paths are relative and the "File path does not match the namespace" check no longer flags every class ([#6](https://github.com/mertbag96/laravel-domain-migrator/issues/6), thanks [@mhmadahmd](https://github.com/mhmadahmd)).

### Added

- Regression test for backslash paths via `ProjectRelativePath` ([#7](https://github.com/mertbag96/laravel-domain-migrator/pull/7)).

## [0.4.3] - 2026-10-18

Documentation and repository polish ahead of public launch.

### Added

- [CONTRIBUTING.md](CONTRIBUTING.md), [SECURITY.md](SECURITY.md), and optional GitHub issue and pull request templates (no enforced commit or PR format).
- README **Real-world examples** for [Snipe-IT](https://github.com/grokability/snipe-it) and [Koel](https://github.com/koel/koel) with scale and readiness notes.
- `art/social-preview.png` (1280×640) for the GitHub repository social preview.

### Changed

- Refreshed README screenshots (`art/html-report.png`, `art/where-to-start.png`) from Snipe-IT on v0.4.2.

## [0.4.2] - 2026-10-18

Saved HTML and JSON reports now show which Laravel application they came from.

### Added

- HTML and JSON reports include the application name (`config('app.name')`) and project root path so a saved report file shows which app it belongs to.

## [0.4.1] - 2026-10-18

Analyze now ranks every suggested domain for extraction readiness, and the HTML report is rebuilt for domain-level planning.

### Added

- Move readiness in `domain:analyze`: each suggested domain is planned once from a single project scan. Console output lists **Where to start** (easiest domains first). JSON and HTML reports include status (`ready`, `needs_preparation`, `blocked`), risk counts and the same ordering as `domain:plan`.
- Redesigned self-contained HTML report: domain bubble graph with drill-down to classes, domain dependency matrix, detail panel (readiness, move targets, risks, depends on / used by), ranked **Where to start** list, light/dark/auto theme, hash links for shareable state, class search, labels toggle and PNG export.

### Changed

- `PlanMigrationAction` can plan all domains in one scan (`executeAll`), reused by readiness assessment.
- `ClassNameMatcher` uses a single generic FQCN pattern with a hash-set lookup (same matches as before).

## [0.4.0] - 2026-10-18

Migration planning: see what moving a domain would change and break, before changing anything.

### Added

- `domain:plan {domain}` command that lists where each class of a domain would move in `app/Domain/{Name}`, keeping its path below the domain, and which files refer to the moved classes. Targets and file paths follow the PSR-4 roots in `composer.json`. It exits with `0`, or `2` for an unknown domain, an invalid map or invalid configuration.
- Risk detection for the move, in three levels. Blockers: classes outside PSR-4, files at the wrong path, files with more than one class, existing and duplicate targets. Breaking: polymorphic types without a morph map (including Notifiable, Sanctum, Spatie and Auditing traits), queued payloads and the models they carry, database notifications, broadcast event names, factory and policy guessing, and listener, command, Livewire, Filament and Nova discovery. Review: class names in strings, Blade templates and JSON or YAML files. Risks the app already handles, such as morph map entries, `databaseType()`, `broadcastAs()`, `#[UseFactory]` and explicit registrations, are left out. Each risk comes with a fix written for the affected classes, such as the morph map entries to add or the line that loads the new commands directory in `bootstrap/app.php` or `app/Console/Kernel.php`.
- `--format=json` for `domain:plan`, with every move, risk and reference.
- `domain.reference_paths` configuration option: where `domain:plan` looks for references to moved classes.

### Removed

- The unused `domain.path` configuration option. The directory of a domain now follows from `domain.namespace` and the PSR-4 roots in `composer.json`.

## [0.3.2] - 2026-10-18

Boundary checks run in your Pest suite, from the same domain map as `domain:check` and PHPStan.

### Added

- Pest arch preset: `arch()->preset()->domains()` fails on the same boundary violations as `domain:check` and shows the same report. It is registered automatically on Pest 3 and newer, honors `domain-baseline.json`, and reads `config/domain-migrator.php` when the application is not booted.

## [0.3.1] - 2026-10-17

Boundary violations show up in PHPStan, next to the rest of your static analysis.

### Added

- PHPStan rule that reports the same boundary violations as `domain:check`, with the identifier `domainMigrator.boundary`. It is registered automatically with `phpstan/extension-installer`, reads `domain-map.json` and `domain-baseline.json`, and does nothing until the map exists. On PHPStan 2.3 and newer, editing the map or the baseline re-analyses the affected files.

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

- Domain maps use schema 2: each domain has `depends_on` (pre-filled with today's domain-to-domain dependencies) and `namespaces`; `cohesion` and `pinned` stay in the analysis report only. Schema 1 maps are still read but not checked; `domain:analyze --update --map=domain-map.json` upgrades them, keeping every assignment and adding `depends_on` from today's dependencies.

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

[Unreleased]: https://github.com/mertbag96/laravel-domain-migrator/compare/v0.6.0...HEAD
[0.6.0]: https://github.com/mertbag96/laravel-domain-migrator/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/mertbag96/laravel-domain-migrator/compare/v0.4.4...v0.5.0
[0.4.4]: https://github.com/mertbag96/laravel-domain-migrator/compare/v0.4.3...v0.4.4
[0.4.3]: https://github.com/mertbag96/laravel-domain-migrator/compare/v0.4.2...v0.4.3
[0.4.2]: https://github.com/mertbag96/laravel-domain-migrator/compare/v0.4.1...v0.4.2
[0.4.1]: https://github.com/mertbag96/laravel-domain-migrator/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/mertbag96/laravel-domain-migrator/compare/v0.3.2...v0.4.0
[0.3.2]: https://github.com/mertbag96/laravel-domain-migrator/compare/v0.3.1...v0.3.2
[0.3.1]: https://github.com/mertbag96/laravel-domain-migrator/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/mertbag96/laravel-domain-migrator/compare/v0.2.1...v0.3.0
[0.2.1]: https://github.com/mertbag96/laravel-domain-migrator/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/mertbag96/laravel-domain-migrator/releases/tag/v0.2.0
