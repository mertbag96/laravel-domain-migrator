---
name: domain-migrator
description: Analyze a Laravel app's domains, enforce domain boundaries, and plan, move or roll back a domain with mertbag96/laravel-domain-migrator (domain:analyze, domain:check, domain:plan, domain:move, domain:rollback, domain-map.json).
---

# Laravel Domain Migrator

## When to use this skill

Use it when the user wants to:
- find domains or modules in the app
- decide which domain to extract first
- set up or fix domain boundary checks (`domain:check`, PHPStan, Pest)
- move a domain to `app/Domain/{Name}`, or undo a move
- deal with a failing `domain:check`, a `domainMigrator.boundary` PHPStan error or a failing `arch()->preset()->domains()` test

## Commands

| Command | Writes files? | Purpose |
|---|---|---|
| `php artisan domain:analyze [--format=console\|json\|html] [--output=file]` | Only the report or map you ask for | Suggest domains, rank move readiness ("where to start"), list coupling hot spots |
| `php artisan domain:analyze --map=domain-map.json [--update] [--force]` | The domain map | Write the suggested domains. `--update` keeps reviewed domains and rules and only places new classes. `--force` overwrites an existing map |
| `php artisan domain:check [--format=console\|json\|github] [--generate-baseline]` | Only with `--generate-baseline` | Fail on dependencies that `depends_on` does not allow. Exit codes: `0` pass, `1` new violations, `2` invalid map, baseline or config |
| `php artisan domain:plan {Domain} [--format=console\|json]` | No | Show target paths, the files that refer to the domain's classes, and risks |
| `php artisan domain:move {Domain} [--dry-run] [--force] [--no-backup] [--verify]` | Yes, rewrites app code | Move the domain's files and update namespaces, imports, strings, views, data files and the map. `--verify` then checks the result and exits `1` on problems (`2` for an invalid map or config), leaving the move in place (see below) |
| `php artisan domain:rollback [id] [--list] [--dry-run]` | Yes | Restore files from a backup written by `domain:move` |

Prefer `--format=json` when you need to parse the output: `domain:analyze --format=json` and `domain:plan {Domain} --format=json` contain every class, dependency, risk and reference. Every field is described in `vendor/mertbag96/laravel-domain-migrator/docs/json-output.md`.

## The domain map

`domain-map.json` assigns classes to domains and lists which domains each may use:

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

- `classes` wins over `namespaces`. Otherwise the longest matching namespace wins.
- `shared` classes may be used by every domain. `unassigned` classes are not checked.
- Removing `depends_on` from a domain stops checking it.
- Suggested domains are a starting point. Domain names and placement are the user's decisions, so show your proposed map edits instead of rewriting the map silently.

## Workflow: enforce boundaries

1. `php artisan domain:analyze --map=domain-map.json`, then review the map with the user.
2. Tighten `depends_on` to the rules the user wants.
3. `php artisan domain:check --generate-baseline` to accept today's violations, so only new ones fail.
4. Add `php artisan domain:check --format=github` to CI. PHPStan 2.1+ and Pest 3+ (`arch()->preset()->domains();`) report the same violations from the same map.
5. When new classes appear, run `php artisan domain:analyze --update --map=domain-map.json` and review the diff: where a class lands decides which rules apply to it.

When a check fails, remove the dependency (move the logic, use an event or depend on a shared class). Only add the domain to `depends_on` or regenerate the baseline when the user confirms the dependency is intended. Never edit `domain-baseline.json` by hand to hide a violation.

## Workflow: move a domain

1. Pick the domain from "where to start" in `domain:analyze`.
2. `php artisan domain:plan {Domain}`. Fix every **blocker** first: `domain:move` refuses to run while any blocker remains.
3. Handle every **breaking** risk before moving, or agree with the user how it will be handled before deploy. The tool does **not** fix these. The plan prints a fix for each, for example:
   - polymorphic types: add a `Relation::morphMap()` alias and migrate existing `*_type` values
   - model factories: add `#[UseFactory(...)]` to the model and `protected $model` to the factory
   - also queued payloads, database notifications, broadcast names, policy guessing, and listener, command, Livewire, Filament and Nova discovery
4. Make sure you are on a git branch with a clean working tree.
5. `php artisan domain:move {Domain} --dry-run` and read every path with the user. Suggested domains can contain classes that belong elsewhere, so edit `domain-map.json` until the dry run looks right. Dry runs are also refused while breaking risks remain: use `--dry-run --force` only to preview.
6. `php artisan domain:move {Domain} --verify`. Add `--force` only with the user's explicit approval, after the breaking risks are handled. Never use `--no-backup` unless the user asks.
7. If `--verify` exits with a failure, read the listed problems (an old file that still exists, a file that is missing or no longer parses, classes the plan still wants to move, or a leftover reference to an old class name such as a grouped import). The move is kept, so fix them by hand or restore with `domain:rollback`. `--verify` does nothing on `--dry-run`, and it does not run your tests or `domain:check`.
8. `composer dump-autoload`, then run the test suite and `domain:check`.
9. Fix what the move lists as "Needs a look by hand". Grouped imports (`use App\Models\{A, B}`) and class names in comments are not rewritten.

## Undo a move

`php artisan domain:rollback --list` lists backups, `domain:rollback --dry-run` previews a restore, and `domain:rollback` restores the latest backup (or pass an id). Backups live in `.domain-migrator/backups/`, which should be in `.gitignore`. A rollback restores file contents only: run `composer dump-autoload` and the tests afterwards. If the work is committed on a branch, `git` is often the simpler undo.

## Configuration

`config/domain-migrator.php` (publish it with `php artisan vendor:publish --tag=domain-migrator-config`) controls the scan `paths` and `exclude`, `hints` (pin classes to a named domain), `clustering.resolution` (raise it for more, smaller domains), the dependency `weights`, the map and baseline paths, the target `domain.namespace` (default `App\Domain`), and the backup settings.
