<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Enums;

enum PlanFormat: string
{
    case Console = 'console';
    case Json = 'json';
}
