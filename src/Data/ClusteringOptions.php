<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;

final readonly class ClusteringOptions
{
    /**
     * @param  array<string, list<string>>  $hints  Seed classes keyed by domain name.
     * @param  float  $resolution  Higher values produce more, smaller domains.
     * @param  int  $sharedMinDomains  A non-model class used by at least this many other domains is treated as shared.
     * @param  int  $baseTypeThreshold  A parent class, interface or trait with at least this many children is a
     *                                  technical base type; inheriting from it does not pull classes into one domain.
     * @param  list<string>  $shared  Classes that are always shared, for example from a reviewed domain map.
     */
    public function __construct(
        public array $hints = [],
        public float $resolution = 1.0,
        public int $sharedMinDomains = 3,
        public int $baseTypeThreshold = 5,
        public array $shared = [],
    ) {}

    /**
     * Keeps the assignments of a reviewed domain map; its domains replace configured hints with the same name.
     */
    public function withDomainMap(BoundaryMap $map): self
    {
        return new self(
            hints: [...$this->hints, ...$map->hints()],
            resolution: $this->resolution,
            sharedMinDomains: $this->sharedMinDomains,
            baseTypeThreshold: $this->baseTypeThreshold,
            shared: array_values(array_unique([...$this->shared, ...$map->shared])),
        );
    }

    /**
     * @param  array<mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        $clustering = $config['clustering'] ?? [];

        if (! is_array($clustering)) {
            throw InvalidConfigurationException::invalidValue('clustering', 'an array');
        }

        $resolution = $clustering['resolution'] ?? 1.0;

        if (! is_int($resolution) && ! is_float($resolution) || $resolution <= 0) {
            throw InvalidConfigurationException::invalidValue('clustering.resolution', 'a positive number');
        }

        $sharedMinDomains = $clustering['shared_min_domains'] ?? 3;

        if (! is_int($sharedMinDomains) || $sharedMinDomains < 2) {
            throw InvalidConfigurationException::invalidValue('clustering.shared_min_domains', 'an integer of at least 2');
        }

        $baseTypeThreshold = $clustering['base_type_threshold'] ?? 5;

        if (! is_int($baseTypeThreshold) || $baseTypeThreshold < 2) {
            throw InvalidConfigurationException::invalidValue('clustering.base_type_threshold', 'an integer of at least 2');
        }

        return new self(
            hints: self::hints($config['hints'] ?? []),
            resolution: (float) $resolution,
            sharedMinDomains: $sharedMinDomains,
            baseTypeThreshold: $baseTypeThreshold,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private static function hints(mixed $hints): array
    {
        if (! is_array($hints)) {
            throw InvalidConfigurationException::invalidValue('hints', 'an array of class lists keyed by domain name');
        }

        $result = [];

        foreach ($hints as $domain => $classes) {
            if (! is_string($domain) || $domain === '' || ! is_array($classes) || ! array_is_list($classes)) {
                throw InvalidConfigurationException::invalidValue('hints', 'an array of class lists keyed by domain name');
            }

            foreach ($classes as $class) {
                if (! is_string($class) || $class === '') {
                    throw InvalidConfigurationException::invalidValue("hints.{$domain}", 'a list of class names');
                }

                $result[$domain][] = ltrim($class, '\\');
            }
        }

        return $result;
    }
}
