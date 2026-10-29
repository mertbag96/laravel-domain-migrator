<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Exceptions;

use InvalidArgumentException;

final class MoveRollbackException extends InvalidArgumentException
{
    public static function notFound(string $id): self
    {
        return new self("No move backup found with id [{$id}]. Run domain:rollback --list to see available backups.");
    }

    public static function noneAvailable(): self
    {
        return new self('No move backups found. Backups are created when you run domain:move without --dry-run or --no-backup.');
    }

    public static function basePathMismatch(string $expected, string $actual): self
    {
        return new self("This backup belongs to [{$expected}], but the application base path is [{$actual}].");
    }

    public static function invalidManifest(string $path, string $reason): self
    {
        return new self("The backup manifest at [{$path}] is invalid ({$reason}).");
    }
}
