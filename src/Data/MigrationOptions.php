<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;

final readonly class MigrationOptions
{
    /**
     * @param  string  $namespace  The namespace that migrated domains live under, e.g. App\Domain.
     * @param  list<string>  $referencePaths  Directories searched for references to moved classes; missing ones are skipped.
     */
    public function __construct(
        public string $namespace = 'App\\Domain',
        public array $referencePaths = ['app', 'bootstrap', 'config', 'database', 'routes', 'resources/views', 'tests'],
    ) {}

    public static function fromConfig(mixed $config): self
    {
        if (! is_array($config)) {
            throw InvalidConfigurationException::notAnArray();
        }

        $domain = $config['domain'] ?? [];

        if (! is_array($domain)) {
            throw InvalidConfigurationException::invalidValue('domain', 'an array');
        }

        $namespace = $domain['namespace'] ?? 'App\\Domain';

        if (! is_string($namespace) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', trim($namespace, '\\')) !== 1) {
            throw InvalidConfigurationException::invalidValue('domain.namespace', 'a namespace such as App\\Domain');
        }

        $paths = $domain['reference_paths'] ?? (new self)->referencePaths;

        if (! is_array($paths) || ! array_is_list($paths) || array_filter($paths, fn (mixed $path): bool => ! is_string($path) || $path === '') !== []) {
            throw InvalidConfigurationException::invalidValue('domain.reference_paths', 'a list of directories');
        }

        /** @var list<string> $paths */
        return new self(trim($namespace, '\\'), $paths);
    }
}
