<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Enums\RiskLevel;
use MertBag\DomainMigrator\Enums\RiskType;

final readonly class MigrationRisk
{
    /**
     * @param  string  $title  A short name, e.g. "Polymorphic types".
     * @param  string  $message  What breaks and why.
     * @param  string  $fix  What to do about it before or after moving.
     * @param  list<RiskItem>  $items
     */
    public function __construct(
        public RiskType $type,
        public RiskLevel $level,
        public string $title,
        public string $message,
        public string $fix,
        public array $items,
    ) {}

    /**
     * @return array{type: string, level: string, title: string, message: string, fix: string, items: list<array{class: string, detail: string, file: ?string, line: ?int}>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'level' => $this->level->value,
            'title' => $this->title,
            'message' => $this->message,
            'fix' => $this->fix,
            'items' => array_map(fn (RiskItem $item): array => $item->toArray(), $this->items),
        ];
    }
}
