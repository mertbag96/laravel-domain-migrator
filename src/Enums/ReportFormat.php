<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Enums;

enum ReportFormat: string
{
    case Console = 'console';
    case Json = 'json';
    case Html = 'html';

    public function defaultOutputPath(): ?string
    {
        return match ($this) {
            self::Console, self::Json => null,
            self::Html => 'domain-report.html',
        };
    }
}
