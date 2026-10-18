<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Enums;

enum ReadinessStatus: string
{
    case Ready = 'ready';
    case NeedsPreparation = 'needs_preparation';
    case Blocked = 'blocked';

    public function order(): int
    {
        return match ($this) {
            self::Ready => 0,
            self::NeedsPreparation => 1,
            self::Blocked => 2,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Ready to move',
            self::NeedsPreparation => 'Needs preparation',
            self::Blocked => 'Blocked',
        };
    }
}
