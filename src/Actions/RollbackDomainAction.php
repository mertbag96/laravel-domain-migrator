<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Actions;

use MertBag\DomainMigrator\Data\MoveOptions;
use MertBag\DomainMigrator\Data\MoveSnapshotManifest;
use MertBag\DomainMigrator\Data\RollbackResult;
use MertBag\DomainMigrator\Exceptions\MoveRollbackException;
use MertBag\DomainMigrator\Migration\Move\MoveSnapshotStore;

final readonly class RollbackDomainAction
{
    public function __construct(
        private MoveSnapshotStore $snapshots,
    ) {}

    public function execute(string $basePath, MoveOptions $options, MoveSnapshotManifest $manifest, bool $dryRun = false): RollbackResult
    {
        $basePath = rtrim(str_replace('\\', '/', realpath($basePath) ?: $basePath), '/');

        if ($this->normalizeBase($manifest->basePath) !== $basePath) {
            throw MoveRollbackException::basePathMismatch($manifest->basePath, $basePath);
        }

        $restored = [];
        $removed = [];

        foreach ($manifest->moved as $from => $to) {
            if (! $dryRun) {
                $this->deleteFile($basePath, $to);
            }

            $removed[] = $to;

            if (! $dryRun) {
                $this->restoreFile($basePath, $from, $this->snapshots->originalContents($manifest, $options, $from));
            }

            $restored[] = $from;
        }

        foreach ($manifest->updated as $file) {
            if (isset($manifest->moved[$file])) {
                continue;
            }

            if (! $dryRun) {
                $this->restoreFile($basePath, $file, $this->snapshots->originalContents($manifest, $options, $file));
            }

            $restored[] = $file;
        }

        if (! $dryRun) {
            $this->pruneEmptyDirectories($basePath, array_values($manifest->moved));
        }

        return new RollbackResult($manifest, array_values(array_unique($restored)), $removed, $dryRun);
    }

    private function restoreFile(string $basePath, string $relative, string $contents): void
    {
        $path = $this->absolute($basePath, $relative);

        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0777, true) && ! is_dir(dirname($path))) {
            throw new MoveRollbackException("Could not create directory for [{$relative}].");
        }

        file_put_contents($path, $contents);
    }

    private function deleteFile(string $basePath, string $relative): void
    {
        $path = $this->absolute($basePath, $relative);

        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @param  list<string>  $targetFiles
     */
    private function pruneEmptyDirectories(string $basePath, array $targetFiles): void
    {
        $directories = [];

        foreach ($targetFiles as $to) {
            $directory = dirname($this->absolute($basePath, $to));

            while (str_starts_with($directory, $basePath) && $directory !== $basePath) {
                $directories[$directory] = true;
                $directory = dirname($directory);
            }
        }

        $sorted = array_keys($directories);
        rsort($sorted);

        foreach ($sorted as $directory) {
            if (is_dir($directory) && $this->isDirectoryEmpty($directory)) {
                rmdir($directory);
            }
        }
    }

    private function isDirectoryEmpty(string $directory): bool
    {
        return scandir($directory) === ['.', '..'];
    }

    private function absolute(string $basePath, string $file): string
    {
        $file = str_replace('\\', '/', $file);

        return preg_match('#^(/|[A-Za-z]:[\\\\/])#', $file) === 1 ? $file : $basePath.'/'.$file;
    }

    private function normalizeBase(string $basePath): string
    {
        return rtrim(str_replace('\\', '/', realpath($basePath) ?: $basePath), '/');
    }
}
