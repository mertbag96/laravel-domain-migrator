<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Enums;

enum ReportFormat: string
{
    case Console = 'console';
    case Json = 'json';
}
