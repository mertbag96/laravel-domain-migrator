<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

final readonly class MoveResult
{
    /**
     * @param  array<string, string>  $moved  Old file => new file, relative to the base path.
     * @param  list<string>  $updated  Other files whose class names were rewritten.
     * @param  list<string>  $manual  Places that need a look by hand.
     */
    public function __construct(
        public MigrationPlan $plan,
        public array $moved,
        public array $updated,
        public array $manual,
        public bool $dryRun,
    ) {}
}
