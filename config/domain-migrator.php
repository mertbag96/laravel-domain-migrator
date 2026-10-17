<?php

declare(strict_types=1);

return [

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

];
