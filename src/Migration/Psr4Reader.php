<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration;

use MertBag\DomainMigrator\Data\Psr4Root;
use MertBag\DomainMigrator\Exceptions\InvalidMigrationPlanException;

final readonly class Psr4Reader
{
    /**
     * The PSR-4 roots of the application, longest namespace first. Falls back to Laravel's App\ => app/
     * when there is no composer.json.
     *
     * @return list<Psr4Root>
     *
     * @throws InvalidMigrationPlanException
     */
    public function read(string $basePath): array
    {
        $path = rtrim($basePath, '/').'/composer.json';

        if (! is_file($path)) {
            return [new Psr4Root('App\\', 'app')];
        }

        $composer = json_decode((string) file_get_contents($path), true);
        $mapping = is_array($composer) && is_array($composer['autoload'] ?? null) ? ($composer['autoload']['psr-4'] ?? []) : null;

        if (! is_array($mapping)) {
            throw InvalidMigrationPlanException::invalidComposerJson($path);
        }

        $roots = [];

        foreach ($mapping as $namespace => $directories) {
            $directory = is_array($directories) ? ($directories[0] ?? null) : $directories;

            if (is_string($namespace) && $namespace !== '' && is_string($directory)) {
                $roots[] = new Psr4Root(rtrim($namespace, '\\').'\\', trim($directory, '/'));
            }
        }

        usort($roots, fn (Psr4Root $a, Psr4Root $b): int => strlen($b->namespace) <=> strlen($a->namespace));

        return $roots;
    }
}
