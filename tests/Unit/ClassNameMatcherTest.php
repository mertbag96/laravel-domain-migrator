<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Migration\Scanning\ClassNameMatcher;

it('finds class names written with single, escaped or leading backslashes', function (): void {
    $matcher = new ClassNameMatcher(['App\Models\Post']);

    expect($matcher->match("'App\\Models\\Post'"))->toBe(['App\Models\Post'])
        ->and($matcher->match('{"model": "App\\\\Models\\\\Post"}'))->toBe(['App\Models\Post'])
        ->and($matcher->match('{{ \App\Models\Post::count() }}'))->toBe(['App\Models\Post']);
});

it('does not match longer class names or namespaces', function (): void {
    $matcher = new ClassNameMatcher(['App\Models\Post', 'App\Models\PostTag']);

    expect($matcher->match('App\Models\PostTag and App\Models\Post\Draft and MyApp\Models\Post'))->toBe(['App\Models\PostTag']);
});

it('reports the offset of each match', function (): void {
    expect((new ClassNameMatcher(['App\Models\Post']))->matchWithOffsets("x\nApp\\Models\\Post"))->toBe([['App\Models\Post', 2]]);
});

it('matches nothing without classes', function (): void {
    expect((new ClassNameMatcher([]))->match('App\Models\Post'))->toBe([]);
});
