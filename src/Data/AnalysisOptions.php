<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;

final readonly class AnalysisOptions
{
    /**
     * @param  list<string>  $paths
     * @param  list<string>  $exclude
     */
    public function __construct(
        public string $basePath,
        public array $paths,
        public array $exclude = [],
        public DependencyWeights $weights = new DependencyWeights,
        public int $hotspotLimit = 10,
        public ClusteringOptions $clustering = new ClusteringOptions,
    ) {}

    public static function fromConfig(mixed $config, string $defaultBasePath): self
    {
        if (! is_array($config)) {
            throw InvalidConfigurationException::notAnArray();
        }

        $basePath = $config['base_path'] ?? null;

        if ($basePath !== null && ! is_string($basePath)) {
            throw InvalidConfigurationException::invalidValue('base_path', 'a string or null');
        }

        $report = $config['report'] ?? [];
        $hotspotLimit = is_array($report) ? ($report['hotspots'] ?? 10) : null;

        if (! is_int($hotspotLimit) || $hotspotLimit < 1) {
            throw InvalidConfigurationException::invalidValue('report.hotspots', 'a positive integer');
        }

        return new self(
            basePath: rtrim($basePath ?? $defaultBasePath, '/'),
            paths: self::stringList($config, 'paths'),
            exclude: self::stringList($config, 'exclude'),
            weights: DependencyWeights::fromConfig($config['weights'] ?? []),
            hotspotLimit: $hotspotLimit,
            clustering: ClusteringOptions::fromConfig($config),
        );
    }

    /**
     * @param  array<mixed>  $config
     * @return list<string>
     */
    private static function stringList(array $config, string $key): array
    {
        $value = $config[$key] ?? [];

        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidConfigurationException::invalidValue($key, 'a list of strings');
        }

        foreach ($value as $item) {
            if (! is_string($item) || $item === '') {
                throw InvalidConfigurationException::invalidValue($key, 'a list of strings');
            }
        }

        return $value;
    }
}
