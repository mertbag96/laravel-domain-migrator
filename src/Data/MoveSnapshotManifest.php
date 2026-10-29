<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

final readonly class MoveSnapshotManifest
{
    /**
     * @param  array<string, string>  $moved  Relative from path => relative to path.
     * @param  list<string>  $updated  Relative paths whose contents were rewritten in place.
     * @param  list<string>  $backedUpFiles  Relative paths stored under files/ in the snapshot.
     */
    public function __construct(
        public int $schema,
        public string $id,
        public string $domain,
        public string $createdAt,
        public string $basePath,
        public ?string $mapPath,
        public array $moved,
        public array $updated,
        public array $backedUpFiles,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $schema = $data['schema'] ?? null;
        $id = $data['id'] ?? null;
        $domain = $data['domain'] ?? null;
        $createdAt = $data['created_at'] ?? null;
        $basePath = $data['base_path'] ?? null;
        $mapPath = $data['map_path'] ?? null;
        $moved = $data['moved'] ?? null;
        $updated = $data['updated'] ?? null;
        $backedUpFiles = $data['backed_up_files'] ?? null;

        return new self(
            schema: is_int($schema) ? $schema : (is_numeric($schema) ? (int) $schema : 0),
            id: is_string($id) ? $id : '',
            domain: is_string($domain) ? $domain : '',
            createdAt: is_string($createdAt) ? $createdAt : '',
            basePath: is_string($basePath) ? $basePath : '',
            mapPath: is_string($mapPath) ? $mapPath : null,
            moved: is_array($moved) ? self::stringMap($moved) : [],
            updated: is_array($updated) && array_is_list($updated) ? self::stringList($updated) : [],
            backedUpFiles: is_array($backedUpFiles) && array_is_list($backedUpFiles) ? self::stringList($backedUpFiles) : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema' => $this->schema,
            'id' => $this->id,
            'domain' => $this->domain,
            'created_at' => $this->createdAt,
            'base_path' => $this->basePath,
            'map_path' => $this->mapPath,
            'moved' => $this->moved,
            'updated' => $this->updated,
            'backed_up_files' => $this->backedUpFiles,
        ];
    }

    /**
     * @param  array<mixed, mixed>  $map
     * @return array<string, string>
     */
    private static function stringMap(array $map): array
    {
        $result = [];

        foreach ($map as $from => $to) {
            if (is_string($from) && is_string($to)) {
                $result[$from] = $to;
            }
        }

        return $result;
    }

    /**
     * @param  list<mixed>  $list
     * @return list<string>
     */
    private static function stringList(array $list): array
    {
        return array_values(array_filter($list, fn (mixed $value): bool => is_string($value) && $value !== ''));
    }
}
