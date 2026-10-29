<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Move;

use MertBag\DomainMigrator\Data\MoveOptions;
use MertBag\DomainMigrator\Data\MoveSnapshotManifest;
use MertBag\DomainMigrator\Exceptions\MoveRollbackException;
use MertBag\DomainMigrator\Support\ProjectRelativePath;
use RuntimeException;

/**
 * Writes and reads on-disk snapshots of a domain:move, under the application's base path.
 */
final class MoveSnapshotStore
{
    private const int SCHEMA = 1;

    /**
     * @param  array<string, string>  $moved
     * @param  list<string>  $updated
     * @param  array<string, string>  $originals  Relative path => file contents before the move.
     */
    public function write(
        string $basePath,
        MoveOptions $options,
        string $domain,
        ?string $mapPath,
        array $moved,
        array $updated,
        array $originals,
    ): MoveSnapshotManifest {
        $basePath = $this->normalizeBase($basePath);
        $id = $this->newId($domain);
        $directory = $this->snapshotDirectory($basePath, $options, $id);
        $filesDirectory = $directory.'/files';

        if (! is_dir($filesDirectory) && ! mkdir($filesDirectory, 0777, true) && ! is_dir($filesDirectory)) {
            throw new RuntimeException("Could not create backup directory [{$filesDirectory}].");
        }

        $mapRelative = $mapPath === null ? null : ProjectRelativePath::toRelative($mapPath, $basePath);
        $backedUp = [];

        foreach ($originals as $relative => $contents) {
            $relative = str_replace('\\', '/', $relative);
            $target = $filesDirectory.'/'.$relative;

            if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0777, true) && ! is_dir(dirname($target))) {
                throw new RuntimeException("Could not create backup path for [{$relative}].");
            }

            file_put_contents($target, $contents);
            $backedUp[] = $relative;
        }

        sort($backedUp);

        $manifest = new MoveSnapshotManifest(
            schema: self::SCHEMA,
            id: $id,
            domain: $domain,
            createdAt: gmdate('c'),
            basePath: $basePath,
            mapPath: $mapRelative,
            moved: $moved,
            updated: $updated,
            backedUpFiles: $backedUp,
        );

        $encoded = json_encode($manifest->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        file_put_contents($directory.'/manifest.json', $encoded);

        return $manifest;
    }

    /**
     * @return list<MoveSnapshotManifest>
     */
    public function list(string $basePath, MoveOptions $options): array
    {
        $root = $this->backupsRoot($this->normalizeBase($basePath), $options);

        if (! is_dir($root)) {
            return [];
        }

        $manifests = [];

        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $manifest = $root.'/'.$entry.'/manifest.json';

            if (is_file($manifest)) {
                $manifests[] = $this->readManifest($manifest);
            }
        }

        usort($manifests, fn (MoveSnapshotManifest $a, MoveSnapshotManifest $b): int => strcmp($b->createdAt, $a->createdAt));

        return $manifests;
    }

    public function read(string $basePath, MoveOptions $options, string $id): MoveSnapshotManifest
    {
        $manifest = $this->snapshotDirectory($this->normalizeBase($basePath), $options, $id).'/manifest.json';

        if (! is_file($manifest)) {
            throw MoveRollbackException::notFound($id);
        }

        return $this->readManifest($manifest);
    }

    public function latest(string $basePath, MoveOptions $options): MoveSnapshotManifest
    {
        $manifests = $this->list($basePath, $options);

        if ($manifests === []) {
            throw MoveRollbackException::noneAvailable();
        }

        return $manifests[0];
    }

    public function originalContents(MoveSnapshotManifest $manifest, MoveOptions $options, string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $path = $this->snapshotDirectory($manifest->basePath, $options, $manifest->id).'/files/'.$relativePath;

        if (! is_file($path)) {
            throw MoveRollbackException::invalidManifest($path, 'missing backed-up file');
        }

        return (string) file_get_contents($path);
    }

    private function newId(string $domain): string
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $domain) ?? 'domain');

        return gmdate('Y-m-d\THis\Z').'-'.trim($slug, '-');
    }

    private function normalizeBase(string $basePath): string
    {
        return rtrim(str_replace('\\', '/', realpath($basePath) ?: $basePath), '/');
    }

    private function backupsRoot(string $basePath, MoveOptions $options): string
    {
        return $basePath.'/'.$options->backupPath;
    }

    private function snapshotDirectory(string $basePath, MoveOptions $options, string $id): string
    {
        return $this->backupsRoot($basePath, $options).'/'.$id;
    }

    private function readManifest(string $path): MoveSnapshotManifest
    {
        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw MoveRollbackException::invalidManifest($path, 'expected JSON object');
        }

        /** @var array<string, mixed> $decoded */
        $manifest = MoveSnapshotManifest::fromArray($decoded);

        if ($manifest->schema !== self::SCHEMA || $manifest->id === '') {
            throw MoveRollbackException::invalidManifest($path, 'unsupported schema or missing id');
        }

        return $manifest;
    }
}
