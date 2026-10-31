# JSON output reference

Three commands can write JSON for CI, scripts and AI agents:

| Command | Document | Current `schema` |
|---|---|---|
| `php artisan domain:analyze --format=json [--output=file]` | [Analysis report](#analysis-report) | 1 |
| `php artisan domain:check --format=json` | [Boundary check result](#boundary-check-result) | 1 |
| `php artisan domain:plan {Domain} --format=json` | [Migration plan](#migration-plan) | 1 |

The domain map (`domain-map.json`, schema 2) is documented in the [README](../README.md#domain-map).

Conventions shared by every document:

- Every document starts with an integer `schema` version. The package is pre-1.0: shapes may change between minor releases, and changes are noted in [CHANGELOG.md](../CHANGELOG.md). Check `schema` before parsing.
- Class names are fully qualified, without a leading backslash (`App\\Models\\Post`).
- File paths are relative to the application base path and always use `/`, on Windows too.
- `skipped_files` lists PHP files that could not be parsed, as `{ "file", "reason" }`. Those classes are missing from the rest of the document.

The examples below are trimmed output from the `tests/Fixtures/blog` app. Arrays show one entry.

## Analysis report

```json
{
    "schema": 1,
    "application": { "name": "Laravel", "base_path": "/var/www/blog" },
    "summary": {
        "classes": 12,
        "dependencies": 17,
        "isolated_classes": 0,
        "domains": 3,
        "shared_classes": 0,
        "skipped_files": 0
    },
    "kinds": { "model": 3, "controller": 2, "job": 1, "other": 1 },
    "classes": [
        {
            "name": "App\\Console\\Commands\\PublishScheduledPosts",
            "kind": "command",
            "declaration": "class",
            "file": "app/Console/Commands/PublishScheduledPosts.php",
            "domain": "Controllers",
            "dependents": 0,
            "dependencies": 2,
            "instability": 1.0
        }
    ],
    "dependencies": [
        {
            "source": "App\\Console\\Commands\\PublishScheduledPosts",
            "target": "App\\Jobs\\PublishPost",
            "types": ["usage", "import"],
            "weight": 2
        }
    ],
    "domains": {
        "domains": [
            {
                "name": "Posts",
                "classes": ["App\\Models\\Comment", "App\\Models\\Post", "App\\Policies\\PostPolicy"],
                "cohesion": 0.68,
                "pinned": false
            }
        ],
        "shared": [],
        "unassigned": []
    },
    "hotspots": ["App\\Models\\Post", "App\\Jobs\\PublishPost"],
    "readiness": [
        {
            "domain": "Controllers",
            "namespace": "App\\Domain\\Controllers",
            "status": "needs_preparation",
            "moves": 4,
            "in_place": 0,
            "referencing_files": 3,
            "blockers": 0,
            "breaking": 2,
            "review": 0,
            "preparation_items": 2,
            "risks": [
                { "type": "queued_payload", "level": "breaking", "title": "Queued payloads", "items": 1 }
            ]
        }
    ],
    "skipped_files": []
}
```

| Field | Meaning |
|---|---|
| `application` | App name and absolute base path, or `null` when unknown |
| `summary.isolated_classes` | Classes with no dependency on, or from, another scanned class |
| `kinds` | Class count per `kind`, in the order of [Kinds](#kinds); kinds with no classes are left out |
| `classes[].kind` | See [Kinds](#kinds) |
| `classes[].declaration` | `class`, `interface`, `trait` or `enum` |
| `classes[].domain` | Suggested domain, `"Shared"` for shared-kernel classes, or `null` for unassigned classes |
| `classes[].dependents` / `dependencies` | Afferent / efferent coupling: how many scanned classes use this one, and how many it uses |
| `classes[].instability` | `dependencies / (dependents + dependencies)`, from `0.0` (stable) to `1.0` (unstable) |
| `dependencies[].types` | Every link kind found between the two classes: `relationship`, `inheritance`, `framework_binding`, `usage`, `import` |
| `dependencies[].weight` | Weight of the strongest link, from the `weights` config |
| `domains.domains[].cohesion` | Share of the domain's dependency weight that stays inside it, from `0.0` to `1.0` |
| `domains.domains[].pinned` | Whether the domain was seeded by `hints` in the config |
| `domains.shared` / `unassigned` | Shared-kernel classes, and classes with no dependencies to place by hand |
| `hotspots` | The most depended-on classes, at most `report.hotspots` (default 10) |
| `readiness` | One entry per suggested domain, **sorted from easiest to hardest to move**. Use it to pick where to start |
| `readiness[].status` | `ready` (no blockers or breaking risks), `needs_preparation` (breaking risks) or `blocked` (at least one blocker) |
| `readiness[].preparation_items` | Number of blocker and breaking risk items to handle before moving |
| `readiness[].risks[].items` | Item count. Run `domain:plan` for the items themselves |

## Boundary check result

`domain:check --format=json` prints this document whatever the outcome. The exit code is still `0` (passed), `1` (new violations) or `2` (invalid map, baseline or config). With exit code `2`, only an error message is printed.

```json
{
    "schema": 1,
    "passed": false,
    "outdated_map": false,
    "summary": {
        "classes": 12,
        "violations": 3,
        "baselined": 0,
        "fixed_baseline_entries": 0,
        "unchecked_domains": 0,
        "unmapped_classes": 0,
        "skipped_files": 0
    },
    "violations": [
        {
            "source": "App\\Models\\Post",
            "target": "App\\Models\\User",
            "source_domain": "Posts",
            "target_domain": "Users",
            "file": "app/Models/Post.php",
            "line": 16,
            "types": ["relationship", "usage"]
        }
    ],
    "fixed_baseline_entries": [],
    "unchecked_domains": [],
    "unmapped_classes": [],
    "skipped_files": []
}
```

| Field | Meaning |
|---|---|
| `passed` | `true` when there are no new violations |
| `outdated_map` | The map was written by 0.2 and has no `depends_on`, so nothing is checked. Run `domain:analyze --update --map=...` |
| `summary.baselined` | Violations skipped because they are in the baseline |
| `violations` | New violations only: `source` (in `source_domain`) depends on `target` (in `target_domain`), which `depends_on` does not allow |
| `violations[].line` | Line in `file` where the target is first referenced, or `null` when unknown |
| `fixed_baseline_entries` | Baseline entries (`{ "source", "target" }`) that no longer occur. Regenerate the baseline |
| `unchecked_domains` | Domains without a `depends_on` key, which are not checked |
| `unmapped_classes` | Scanned classes that the map does not assign to any domain |

## Migration plan

```json
{
    "schema": 1,
    "domain": "Posts",
    "namespace": "App\\Domain\\Posts",
    "summary": {
        "moves": 10,
        "in_place": 0,
        "referencing_files": 13,
        "blockers": 0,
        "breaking": 8,
        "review": 1
    },
    "moves": [
        {
            "class": "App\\Console\\Commands\\PublishScheduledPosts",
            "target": "App\\Domain\\Posts\\Console\\Commands\\PublishScheduledPosts",
            "file": "app/Console/Commands/PublishScheduledPosts.php",
            "target_file": "app/Domain/Posts/Console/Commands/PublishScheduledPosts.php",
            "kind": "command"
        }
    ],
    "in_place": [],
    "risks": [
        {
            "type": "morph_type",
            "level": "breaking",
            "title": "Polymorphic types",
            "message": "These models are stored by class name in polymorphic *_type columns, and the app has no morph map. Existing rows keep the old class name, so their relations stop resolving after the move.",
            "fix": "Before moving, give each model an alias in a service provider with Relation::morphMap(['post' => Post::class]) and update the existing *_type values from the class names to the aliases in a migration.",
            "items": [
                {
                    "class": "App\\Models\\Post",
                    "detail": "declares a morphMany() relation",
                    "file": "app/Models/Post.php",
                    "line": 21
                }
            ]
        }
    ],
    "references": [
        {
            "class": "App\\Jobs\\PublishPost",
            "file": "app/Console/Commands/PublishScheduledPosts.php",
            "line": 5,
            "kind": "code"
        }
    ],
    "skipped_files": []
}
```

| Field | Meaning |
|---|---|
| `namespace` | Target namespace of the domain (`domain.namespace` config + domain name) |
| `moves` | Each class that moves: current and target class name and file |
| `in_place` | Domain classes that already live in the target namespace |
| `summary.referencing_files` | Files outside the move that refer to a moving class |
| `risks[].type` | `conflict`, `morph_type`, `queued_payload`, `database_notification`, `broadcast_name`, `factory`, `policy`, `discovery` or `string_reference` |
| `risks[].level` | `blocker` (`domain:move` refuses to run), `breaking` (`domain:move` needs `--force`, and something breaks at runtime until handled) or `review` (check after the move) |
| `risks[].message` / `fix` | What breaks, and how to fix it, written for this app's classes |
| `risks[].items[]` | Affected class, a short `detail`, and `file` / `line` (either may be `null`) |
| `references` | Every reference to a moving class, in the configured `domain.reference_paths` |
| `references[].kind` | `code` (a PHP reference: import, type, `new`, `::class`, ...) or `string` (a class name in a string, Blade view, or JSON/YAML file) |

## Kinds

`model`, `controller`, `form_request`, `resource`, `response`, `middleware`, `job`, `event`, `listener`, `observer`, `policy`, `notification`, `mail`, `command`, `provider`, `action`, `service`, `repository`, `builder`, `rule`, `cast`, `data`, `enum`, `exception`, `interface`, `trait`, `other`.
