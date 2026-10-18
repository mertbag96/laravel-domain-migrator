<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Enums\ClassKind;

final readonly class ClassMove
{
    public function __construct(
        public string $class,
        public string $target,
        public string $file,
        public string $targetFile,
        public ClassKind $kind,
    ) {}

    /**
     * @return array{class: string, target: string, file: string, target_file: string, kind: string}
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'target' => $this->target,
            'file' => $this->file,
            'target_file' => $this->targetFile,
            'kind' => $this->kind->value,
        ];
    }
}
