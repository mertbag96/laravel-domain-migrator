<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Support\ProjectRelativePath;

it('returns a forward-slash relative path when the absolute path uses backslashes', function (): void {
    $base = sys_get_temp_dir().'/domain-migrator-path-'.uniqid('', true);
    mkdir($base.'/app', recursive: true);

    $absolute = $base.'/app/Invoice.php';
    $windowsAbsolute = str_replace('/', '\\', $absolute);
    $windowsBase = str_replace('/', '\\', $base);

    expect(ProjectRelativePath::toRelative($windowsAbsolute, $windowsBase))->toBe('app/Invoice.php');

    array_map(unlink(...), glob($base.'/app/*'));
    rmdir($base.'/app');
    rmdir($base);
});

it('leaves forward-slash paths unchanged on unix', function (): void {
    $base = realpath(sys_get_temp_dir()) ?: sys_get_temp_dir();
    $absolute = $base.'/app/Model.php';

    expect(ProjectRelativePath::toRelative($absolute, $base))->toBe('app/Model.php');
});

it('keeps absolute paths and puts relative ones under the base path', function (string $path, string $expected): void {
    expect(ProjectRelativePath::toAbsolute($path, '/srv/app'))->toBe($expected);
})->with([
    'relative' => ['app/Models/Post.php', '/srv/app/app/Models/Post.php'],
    'relative with backslashes' => ['app\Models\Post.php', '/srv/app/app/Models/Post.php'],
    'unix absolute' => ['/var/www/app/Post.php', '/var/www/app/Post.php'],
    'windows absolute' => ['C:\www\app\Post.php', 'C:/www/app/Post.php'],
    'windows absolute with slashes' => ['D:/www/app/Post.php', 'D:/www/app/Post.php'],
]);
