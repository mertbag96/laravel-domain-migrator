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
    | Where domains live once migrated. The default keeps everything under
    | the existing "App\" PSR-4 root so no autoload changes are needed.
    |
    */

    'domain' => [
        'path' => 'app/Domain',
        'namespace' => 'App\\Domain',
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

];
