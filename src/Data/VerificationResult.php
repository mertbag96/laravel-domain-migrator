<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

final readonly class VerificationResult
{
    /**
     * @param  list<string>  $problems  What is still wrong after the move; empty when the move checks out.
     */
    public function __construct(public array $problems = []) {}

    public function passed(): bool
    {
        return $this->problems === [];
    }
}
