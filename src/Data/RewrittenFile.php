<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

final readonly class RewrittenFile
{
    /**
     * @param  list<string>  $manual  Places the rewrite could not update and that need a look by hand.
     */
    public function __construct(
        public string $code,
        public array $manual = [],
    ) {}
}
