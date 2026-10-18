# Contributing

Thanks for considering a contribution.

There is no required process beyond what GitHub enforces on `main`: changes land through a **pull request**, and **CI must pass** before merge. Commit messages, branch names, and PR descriptions are entirely up to you.

## Pull requests

1. Fork the repository and work on a branch on your fork (or ask to be added as a collaborator).
2. Open a pull request against `main` when you are ready.
3. If CI fails, fix what you can or ask for help in the PR — maintainers merge when checks are green.

Large or ambiguous changes (new commands, breaking behavior) are easier to review if you open an **issue** first, but that is optional.

## Local checks

If you want to run the same checks as CI:

```bash
composer test
composer analyse
composer format
```

## Pre-1.0

This package is **pre-1.0**. Public APIs and report JSON may change between minor releases. We will note breaking changes in [CHANGELOG.md](CHANGELOG.md).

## Security

See [SECURITY.md](SECURITY.md) for reporting vulnerabilities.
