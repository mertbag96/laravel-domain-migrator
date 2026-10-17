<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Enums\DeclarationType;
use MertBag\DomainMigrator\Enums\DependencyType;

final readonly class ScannedClass
{
    /**
     * @param  list<string>  $interfaces
     * @param  array<string, list<DependencyType>>  $dependencies  Keyed by the fully qualified target class name.
     * @param  list<string>  $handlerParameters  Types of the first parameter of handle*() and __invoke() methods.
     * @param  array<string, int>  $lines  Line of the first reference to each dependency, keyed like $dependencies.
     */
    public function __construct(
        public string $name,
        public DeclarationType $declaration,
        public string $file,
        public ?string $parent = null,
        public array $interfaces = [],
        public array $dependencies = [],
        public array $handlerParameters = [],
        public array $lines = [],
    ) {}
}
