<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Enums;

enum RiskLevel: string
{
    /** The domain cannot be moved until this is fixed. */
    case Blocker = 'blocker';

    /** Moving the domain breaks something at runtime unless this is handled. */
    case Breaking = 'breaking';

    /** Needs a look by hand; the move cannot update it automatically. */
    case Review = 'review';

    public function order(): int
    {
        return match ($this) {
            self::Blocker => 0,
            self::Breaking => 1,
            self::Review => 2,
        };
    }
}
