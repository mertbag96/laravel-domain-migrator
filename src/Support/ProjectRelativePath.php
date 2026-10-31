<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Support;

final class ProjectRelativePath
{
    public static function toRelative(string $absolutePath, string $basePath): string
    {
        $absolutePath = str_replace('\\', '/', $absolutePath);
        $basePath = str_replace('\\', '/', realpath($basePath) ?: $basePath).'/';

        return str_starts_with($absolutePath, $basePath)
            ? substr($absolutePath, strlen($basePath))
            : $absolutePath;
    }

    public static function toAbsolute(string $path, string $basePath): string
    {
        $path = str_replace('\\', '/', $path);

        return preg_match('#^(/|[A-Za-z]:/)#', $path) === 1 ? $path : $basePath.'/'.$path;
    }
}
