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
    | while scanning.
    |
    */

    'exclude' => [
        'app/Providers/*',
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
    | A class used by at least "shared_min_domains" other domains is moved
    | to the shared kernel instead of being forced into a single domain.
    |
    */

    'clustering' => [
        'resolution' => 1.0,
        'shared_min_domains' => 3,
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
