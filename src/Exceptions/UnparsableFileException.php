<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Exceptions;

use RuntimeException;
use Throwable;

final class UnparsableFileException extends RuntimeException
{
    public static function unreadable(string $file): self
    {
        return new self("The file [{$file}] could not be read.");
    }

    public static function syntaxError(Throwable $previous): self
    {
        return new self($previous->getMessage(), previous: $previous);
    }
}
