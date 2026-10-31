## Laravel Domain Migrator

This app uses `mertbag96/laravel-domain-migrator` to analyze how its classes cluster into domains, enforce domain boundaries, and move a domain to `app/Domain/{Name}`. For the full workflow, activate the `domain-migrator` skill.

- `domain-map.json` is the source of truth for domains, which domains each may depend on (`depends_on`), and shared classes. `domain-baseline.json` lists accepted, known violations. Both are committed.
- Before committing changes that add dependencies between classes, run `php artisan domain:check`. Exit code `1` means a new boundary violation and `2` means an invalid map, baseline or config. Fix a violation by removing the dependency. Only add a domain to `depends_on`, or regenerate the baseline, when the user approves.
- `domain:analyze`, `domain:plan` and `domain:check` are read-only. `domain:move` rewrites files: run it only when the user asks, on a git branch with a clean working tree, after `domain:plan` and a `--dry-run`. Never pass `--force` or `--no-backup` without the user's explicit approval.
- After a move, run `composer dump-autoload` and the test suite. Undo a move with `php artisan domain:rollback`.
