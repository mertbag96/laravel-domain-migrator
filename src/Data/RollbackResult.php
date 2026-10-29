<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

final readonly class RollbackResult
{
    /**
     * @param  list<string>  $restored  Relative paths written back from the snapshot.
     * @param  list<string>  $removed  Relative paths deleted (move targets).
     */
    public function __construct(
        public MoveSnapshotManifest $manifest,
        public array $restored,
        public array $removed,
        public bool $dryRun,
    ) {}
}
