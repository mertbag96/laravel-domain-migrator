<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

/**
 * The reviewed domain map: which domain every class belongs to and which domains each one may use.
 */
final readonly class BoundaryMap
{
    public const int SCHEMA_VERSION = 2;

    /** @var array<string, string> */
    private array $classIndex;

    /** @var array<string, true> */
    private array $unassignedIndex;

    /** @var array<string, string> Namespace prefix with trailing backslash => domain, longest first. */
    private array $namespaceIndex;

    /**
     * @param  list<DomainDefinition>  $domains
     * @param  list<string>  $shared  Classes every domain may use.
     * @param  list<string>  $unassigned  Classes that are deliberately left out of every domain.
     * @param  int  $schema  The schema the map was read with; maps are always written with the current one.
     */
    public function __construct(
        public array $domains = [],
        public array $shared = [],
        public array $unassigned = [],
        public int $schema = self::SCHEMA_VERSION,
    ) {
        $classIndex = array_fill_keys($shared, DomainMap::SHARED);
        $namespaceIndex = [];

        foreach ($domains as $domain) {
            $classIndex += array_fill_keys($domain->classes, $domain->name);

            foreach ($domain->namespaces as $namespace) {
                $namespaceIndex[trim($namespace, '\\').'\\'] ??= $domain->name;
            }
        }

        uksort($namespaceIndex, fn (string $a, string $b): int => [strlen($b), $a] <=> [strlen($a), $b]);

        $this->classIndex = $classIndex;
        $this->unassignedIndex = array_fill_keys($unassigned, true);
        $this->namespaceIndex = $namespaceIndex;
    }

    /**
     * The domain name, DomainMap::SHARED, or null for unassigned and unmapped classes.
     * Classes listed explicitly win over namespaces; the longest matching namespace wins otherwise.
     */
    public function domainOf(string $class): ?string
    {
        if (isset($this->classIndex[$class])) {
            return $this->classIndex[$class];
        }

        if (isset($this->unassignedIndex[$class])) {
            return null;
        }

        foreach ($this->namespaceIndex as $prefix => $domain) {
            if (str_starts_with($class, $prefix)) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * Maps from before schema 2 could not express depends_on, so none of their domains are checked yet.
     */
    public function isOutdated(): bool
    {
        return $this->schema < self::SCHEMA_VERSION;
    }

    public function isMapped(string $class): bool
    {
        return isset($this->unassignedIndex[$class]) || $this->domainOf($class) !== null;
    }

    public function domain(string $name): ?DomainDefinition
    {
        foreach ($this->domains as $domain) {
            if ($domain->name === $name) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * Every listed class, grouped by domain, to seed clustering when the map is updated.
     *
     * @return array<string, list<string>>
     */
    public function hints(): array
    {
        $hints = [];

        foreach ($this->domains as $domain) {
            if ($domain->classes !== []) {
                $hints[$domain->name] = $domain->classes;
            }
        }

        return $hints;
    }

    /**
     * @return array{schema: int, domains: list<array{name: string, depends_on?: list<string>, namespaces: list<string>, classes: list<string>}>, shared: list<string>, unassigned: list<string>}
     */
    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA_VERSION,
            'domains' => array_map(fn (DomainDefinition $domain): array => [
                'name' => $domain->name,
                ...($domain->dependsOn === null ? [] : ['depends_on' => $domain->dependsOn]),
                'namespaces' => $domain->namespaces,
                'classes' => $domain->classes,
            ], $this->domains),
            'shared' => $this->shared,
            'unassigned' => $this->unassigned,
        ];
    }
}
