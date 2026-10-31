# AGENTS.md

Guidance for AI coding agents (Claude Code, Codex, Cursor, Copilot, etc.) working in this repository.

## What this is

A Laravel package (`mertbag96/laravel-domain-migrator`, PHP 8.3+, Laravel 12/13) that statically analyzes a Laravel MVC app, suggests domains, enforces domain boundaries, and plans/executes moving a domain to `app/Domain/{Name}`. Pre-1.0: public APIs and report JSON may change between minors, but breaking changes must be noted in `CHANGELOG.md`. `README.md` is the user-facing reference for every command and option.

## Commands

```bash
composer test                              # Pest (suites: Unit, Feature, PHPStan, Arch)
vendor/bin/pest --filter=PlanCommandTest   # single test file / test name
vendor/bin/pest tests/Unit/LouvainTest.php # single file
vendor/bin/pest --testsuite=Arch           # architecture rules only
composer analyse                           # PHPStan level max on src + config
composer format                            # Pint (CI runs `pint --test`)
php scripts/domains.php /path/to/app [--html=report.html]  # analyze any app without installing the package
```

Tests run in random order with `failOnRisky`/`failOnWarning`. CI matrix: PHP 8.3–8.5 × Laravel 12/13 × prefer-lowest/prefer-stable, so don't rely on features newer than the lowest supported dependency versions.

## Architecture

Five Artisan commands (`domain:analyze`, `domain:check`, `domain:plan`, `domain:move`, `domain:rollback`) are thin wrappers: each resolves an Action from `src/Actions` and hands the result to a renderer in `src/Reporting`. Config is turned into option DTOs (`AnalysisOptions`, `BoundaryOptions`, `MigrationOptions`, `MoveOptions`) via `::fromConfig()` bindings in `DomainMigratorServiceProvider`.

Pipeline:

1. **Scan** (`Analysis/Scanning`): nikic/php-parser walks the configured `paths`, and `DependencyVisitor` records each class's references to other classes, typed by `DependencyType` (relationship, inheritance, framework_binding, usage, import). Vendor and framework classes are dropped.
2. **Graph** (`Analysis/Graph`): `GraphBuilder` builds a weighted `DependencyGraph` (edge weight = strongest link, weights from config); `KindResolver` assigns each class a `ClassKind`.
3. **Cluster** (`Analysis/Clustering`): deterministic `Louvain` plus `DomainDetector` post-processing (hints, base-type inheritance ignored, shared kernel, unassigned, naming).
4. **Report**: `MetricsCalculator` handles coupling/hot spots, and `AssessReadinessAction` runs the planner over every suggested domain to rank "where to start".

**Domain map** (`domain-map.json`, schema 1/2, read by `Boundaries/DomainMapReader` into `BoundaryMap`) is the contract between phases. `BuildDomainMapAction` writes it, and the boundary checks and migration read it. One `BoundaryChecker` backs three front ends that must report identically: `domain:check`, the PHPStan rule (`src/PHPStan`, registered via `extension.neon`), and the Pest arch preset (`src/Pest`, which runs **without a booted Laravel app** and reads `config/domain-migrator.php` itself).

**Migration** (`src/Migration`): `PlanMigrationAction` resolves targets from the composer.json PSR-4 roots (`Psr4Reader`, `TargetResolver`). It then does a second, broader scan (`Migration/Scanning/ProjectScanner`, over `domain.reference_paths`, including Blade/JSON/YAML) to find references to moved classes, and runs every `RiskDetector`. Detectors are listed in `DomainMigratorServiceProvider::RISK_DETECTORS`, so a new detector must be added there. `MoveDomainAction` executes a plan by rewriting PHP with `Migration/Rewriting` (AST visitor + format-preserving printing), snapshotting touched files first via `MoveSnapshotStore`, and auto-restoring on failure. `RollbackDomainAction` restores a snapshot.

## Enforced conventions

`tests/ArchTest.php` enforces these, and they fail the build:

- `declare(strict_types=1)` everywhere; classes are `final` (Pint `final_class` rule).
- `src/Data` classes are final + readonly; `src/Exceptions` are final.
- Commands must not use `PhpParser`, so logic belongs in Actions.
- Layering: `Analysis` must not use Commands/Reporting/Boundaries/Migration/`Illuminate\Console`; `Boundaries` must not use Clustering or the console layer; `Migration` must not use Boundaries, Clustering or the console layer.
- PHPStan and Pest are optional dependencies, so only `src/PHPStan` may reference PHPStan and only `src/Pest` may reference Pest.
- Risk detectors are final and implement `RiskDetector`.

Paths shown to users must be project-relative and Windows-safe. Use `Support/ProjectRelativePath` instead of string-slicing base paths (see `ProjectRelativePathTest` for the regression).

## Laravel Boost resources

`resources/boost/guidelines/core.blade.php` (always loaded, keep it short) and `resources/boost/skills/domain-migrator/SKILL.md` (on demand) teach agents in users' apps how to use the package. When a command, option, exit code, config key or safety behavior changes, update them in the same change. Likewise, keep `docs/json-output.md` in sync with the `toArray()` methods of `AnalysisReport`, `BoundaryCheckResult` and `MigrationPlan`. The guideline is compiled by Blade, so wrap any `@` or `{{ }}` in `@verbatim`. The skill is plain Markdown.

## Changelog

For any user-visible change (behavior, commands, options, config, report JSON, domain map), add an entry under `## [Unreleased]` in `CHANGELOG.md` in the same change, using Keep a Changelog groups (`Added`, `Changed`, `Fixed`, `Removed`) and the existing writing style. Mark breaking changes explicitly. Never create version headings, pick version numbers or edit `composer.json` versions: the maintainer does releases by hand. Internal refactors and test-only changes need no entry.

## Tests

- Feature tests use Orchestra Testbench; PHPStan rule tests extend `tests/PHPStan/RuleTestCase`.
- Two fixture apps: `tests/Fixtures/shop` (analysis/clustering, `fixturePath()`) and `tests/Fixtures/blog` (planning/moving, `blogPath()`, `blogMap()`). Tests that write files must use `blogCopy()`, which makes a disposable temp copy. Never mutate fixtures in place.
- Helpers such as `graphOf()` (build a graph from class names) and `temporaryPhpFile()` live in `tests/Pest.php`.
- `tests/Fixtures/shop/app/Support/Broken.php` is intentionally unparsable, to test skipped-file handling. Pint excludes `tests/Fixtures`.
