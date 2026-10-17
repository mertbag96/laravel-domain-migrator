<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

final readonly class DomainMap
{
    public const string SHARED = 'Shared';

    /**
     * @param  list<Domain>  $domains
     * @param  list<string>  $shared  Classes used across many domains.
     * @param  list<string>  $unassigned  Classes without any internal dependency.
     */
    public function __construct(
        public array $domains = [],
        public array $shared = [],
        public array $unassigned = [],
    ) {}

    public function domainOf(string $class): ?string
    {
        if (in_array($class, $this->shared, true)) {
            return self::SHARED;
        }

        foreach ($this->domains as $domain) {
            if (in_array($class, $domain->classes, true)) {
                return $domain->name;
            }
        }

        return null;
    }

    /**
     * @return array{domains: list<array{name: string, classes: list<string>, cohesion: float, pinned: bool}>, shared: list<string>, unassigned: list<string>}
     */
    public function toArray(): array
    {
        return [
            'domains' => array_map(fn (Domain $domain): array => [
                'name' => $domain->name,
                'classes' => $domain->classes,
                'cohesion' => $domain->cohesion,
                'pinned' => $domain->pinned,
            ], $this->domains),
            'shared' => $this->shared,
            'unassigned' => $this->unassigned,
        ];
    }
}
