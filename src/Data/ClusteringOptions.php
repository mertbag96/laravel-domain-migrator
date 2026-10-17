<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;

final readonly class ClusteringOptions
{
    /**
     * @param  array<string, list<string>>  $hints  Seed classes keyed by domain name.
     * @param  float  $resolution  Higher values produce more, smaller domains.
     * @param  int  $sharedMinDomains  A class used by at least this many other domains is treated as shared.
     */
    public function __construct(
        public array $hints = [],
        public float $resolution = 1.0,
        public int $sharedMinDomains = 3,
    ) {}

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

        return new self(
            hints: self::hints($config['hints'] ?? []),
            resolution: (float) $resolution,
            sharedMinDomains: $sharedMinDomains,
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
