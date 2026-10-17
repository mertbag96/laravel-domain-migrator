# Changelog

All notable changes to `laravel-domain-migrator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `domain:analyze` command with console and JSON (`--format=json`, `--output=`) reports.
- Static scanner that collects relationships, inheritance, attribute bindings, listener handlers, type-hints, instantiations and imports for every class, without executing application code.
- Laravel role detection (model, controller, job, listener, ...) from base classes, namespaces and class names.
- Weighted dependency graph limited to application classes, with configurable weights.
- Coupling metrics per class (dependents, dependencies, instability) and a ranked list of hot spots.
- Unparsable files are reported instead of aborting the analysis.
- Package skeleton: service provider, publishable config, Pest, Pint, Larastan and GitHub Actions.
