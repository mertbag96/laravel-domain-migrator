<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Boundaries;

use Illuminate\Filesystem\Filesystem;
use JsonException;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\DomainDefinition;
use MertBag\DomainMigrator\Exceptions\InvalidDomainMapException;

/**
 * Reads domain maps written by domain:analyze --map (schema 1 and 2) after a team has reviewed them.
 */
final readonly class DomainMapReader
{
    private const array SUPPORTED_SCHEMAS = [1, 2];

    public function __construct(
        private Filesystem $files,
    ) {}

    /**
     * @throws InvalidDomainMapException
     */
    public function read(string $path): BoundaryMap
    {
        if (! $this->files->isFile($path)) {
            throw InvalidDomainMapException::notFound($path);
        }

        try {
            $data = json_decode($this->files->get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidDomainMapException::invalidJson($path, $exception);
        }

        return $this->parse($data, $path);
    }

    /**
     * @throws InvalidDomainMapException
     */
    public function parse(mixed $data, string $path): BoundaryMap
    {
        if (! is_array($data) || ! in_array($data['schema'] ?? null, self::SUPPORTED_SCHEMAS, true)) {
            throw InvalidDomainMapException::unsupportedSchema($path);
        }

        $rawDomains = $data['domains'] ?? [];

        if (! is_array($rawDomains) || ! array_is_list($rawDomains)) {
            throw InvalidDomainMapException::invalidValue($path, 'domains', 'a list of domains');
        }

        $domains = [];

        foreach ($rawDomains as $index => $domain) {
            $domains[] = $this->domain($domain, "domains.{$index}", $path);
        }

        $map = new BoundaryMap(
            domains: $domains,
            shared: $this->classList($data['shared'] ?? [], 'shared', $path),
            unassigned: $this->classList($data['unassigned'] ?? [], 'unassigned', $path),
        );

        $this->validate($map, $path);

        return $map;
    }

    private function domain(mixed $domain, string $key, string $path): DomainDefinition
    {
        if (! is_array($domain)) {
            throw InvalidDomainMapException::invalidValue($path, $key, 'an object');
        }

        $name = $domain['name'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            throw InvalidDomainMapException::invalidValue($path, "{$key}.name", 'a non-empty string');
        }

        return new DomainDefinition(
            name: $name,
            classes: $this->classList($domain['classes'] ?? [], "{$key}.classes", $path),
            namespaces: array_map(
                fn (string $namespace): string => trim($namespace, '\\'),
                $this->stringList($domain['namespaces'] ?? [], "{$key}.namespaces", 'a list of namespaces', $path),
            ),
            dependsOn: array_key_exists('depends_on', $domain)
                ? $this->stringList($domain['depends_on'], "{$key}.depends_on", 'a list of domain names', $path)
                : null,
        );
    }

    /**
     * @return list<string>
     */
    private function classList(mixed $value, string $key, string $path): array
    {
        return array_map(
            fn (string $class): string => ltrim($class, '\\'),
            $this->stringList($value, $key, 'a list of class names', $path),
        );
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value, string $key, string $expected, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidDomainMapException::invalidValue($path, $key, $expected);
        }

        $strings = [];

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw InvalidDomainMapException::invalidValue($path, $key, $expected);
            }

            $strings[] = $item;
        }

        return $strings;
    }

    private function validate(BoundaryMap $map, string $path): void
    {
        $names = [];
        $classes = array_fill_keys($map->shared, true);

        foreach ($map->domains as $domain) {
            if (isset($names[$domain->name])) {
                throw InvalidDomainMapException::duplicateDomain($path, $domain->name);
            }

            $names[$domain->name] = true;

            foreach ($domain->classes as $class) {
                if (isset($classes[$class])) {
                    throw InvalidDomainMapException::duplicateClass($path, $class);
                }

                $classes[$class] = true;
            }
        }

        foreach ($map->unassigned as $class) {
            if (isset($classes[$class])) {
                throw InvalidDomainMapException::duplicateClass($path, $class);
            }
        }

        foreach ($map->domains as $domain) {
            foreach ($domain->dependsOn ?? [] as $dependency) {
                if (! isset($names[$dependency])) {
                    throw InvalidDomainMapException::unknownDependency($path, $domain->name, $dependency);
                }
            }
        }
    }
}
