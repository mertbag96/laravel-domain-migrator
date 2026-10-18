<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Enums\ReferenceKind;

final readonly class ClassReference
{
    public function __construct(
        public string $class,
        public string $file,
        public int $line,
        public ReferenceKind $kind,
    ) {}

    /**
     * @return array{class: string, file: string, line: int, kind: string}
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'file' => $this->file,
            'line' => $this->line,
            'kind' => $this->kind->value,
        ];
    }
}
