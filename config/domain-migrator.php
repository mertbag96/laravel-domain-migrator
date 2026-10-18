<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Base Path
    |--------------------------------------------------------------------------
    |
    | The root that scan paths and reported file paths are relative to.
    | Defaults to the application base path when null.
    |
    */

    'base_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Scan Paths
    |--------------------------------------------------------------------------
    |
    | Directories (relative to the application base path) that are parsed
    | when building the dependency graph.
    |
    */

    'paths' => [
        'app',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Paths
    |--------------------------------------------------------------------------
    |
    | Glob patterns (relative to the application base path) that are skipped
    | while scanning. Service providers and kernels only wire the framework
    | together, so they would otherwise link unrelated classes.
    |
    */

    'exclude' => [
        'app/Providers/*',
        'app/Http/Kernel.php',
        'app/Console/Kernel.php',
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Target
    |--------------------------------------------------------------------------
    |
    | Where domains live once migrated: "domain:plan Orders" moves classes
    | to App\Domain\Orders. File paths follow the PSR-4 autoload section of
    | composer.json, so the default needs no autoload changes.
    |
    | References to moved classes are searched for in the reference paths
    | (relative to the base path); paths that do not exist are skipped.
    |
    */

    'domain' => [
        'namespace' => 'App\\Domain',
        'reference_paths' => ['app', 'bootstrap', 'config', 'database', 'routes', 'resources/views', 'tests'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Hints
    |--------------------------------------------------------------------------
    |
    | Seed classes that steer clustering, keyed by domain name.
    |
    | 'Billing' => [App\Models\Invoice::class, App\Models\Payment::class],
    |
    */

    'hints' => [],

    /*
    |--------------------------------------------------------------------------
    | Clustering
    |--------------------------------------------------------------------------
    |
    | "resolution" tunes domain size: raise it for more, smaller domains.
    |
    | A non-model class used by at least "shared_min_domains" other domains
    | is moved to the shared kernel instead of being forced into a single
    | domain. Models always stay in a domain.
    |
    | A parent class, interface or trait with at least "base_type_threshold"
    | children (like a base Controller) is treated as a technical layer:
    | extending it does not pull classes into the same domain.
    |
    */

    'clustering' => [
        'resolution' => 1.0,
        'shared_min_domains' => 3,
        'base_type_threshold' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Edge Weights
    |--------------------------------------------------------------------------
    |
    | How strongly each kind of dependency binds two classes together.
    |
    */

    'weights' => [
        'relationship' => 5,
        'inheritance' => 4,
        'framework_binding' => 3,
        'usage' => 2,
        'import' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Report
    |--------------------------------------------------------------------------
    |
    | "hotspots" is the number of most-depended-on classes to list.
    |
    */

    'report' => [
        'hotspots' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Boundaries
    |--------------------------------------------------------------------------
    |
    | Default files for domain:check, relative to the application base path.
    | The map is created with "domain:analyze --map" and the baseline with
    | "domain:check --generate-baseline". Commit both to version control.
    |
    */

    'boundaries' => [
        'map' => 'domain-map.json',
        'baseline' => 'domain-baseline.json',
    ],

];
