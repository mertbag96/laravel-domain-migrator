<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;

final readonly class BoundaryOptions
{
    public function __construct(
        public string $mapPath,
        public string $baselinePath,
    ) {}

    public static function fromConfig(mixed $config, string $basePath): self
    {
        if (! is_array($config)) {
            throw InvalidConfigurationException::notAnArray();
        }

        $boundaries = $config['boundaries'] ?? [];

        if (! is_array($boundaries)) {
            throw InvalidConfigurationException::invalidValue('boundaries', 'an array');
        }

        return new self(
            mapPath: self::path($boundaries, 'map', 'domain-map.json', $basePath),
            baselinePath: self::path($boundaries, 'baseline', 'domain-baseline.json', $basePath),
        );
    }

    /**
     * @param  array<mixed>  $boundaries
     */
    private static function path(array $boundaries, string $key, string $default, string $basePath): string
    {
        $path = $boundaries[$key] ?? $default;

        if (! is_string($path) || $path === '') {
            throw InvalidConfigurationException::invalidValue("boundaries.{$key}", 'a file path');
        }

        return str_starts_with($path, '/') ? $path : rtrim($basePath, '/').'/'.$path;
    }
}
