<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Enums;

enum CheckFormat: string
{
    case Console = 'console';
    case Json = 'json';
    case Github = 'github';
}
