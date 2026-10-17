<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Exceptions;

use InvalidArgumentException;
use JsonException;

final class InvalidBaselineException extends InvalidArgumentException
{
    public static function invalidJson(string $path, JsonException $previous): self
    {
        return new self("The baseline [{$path}] is not valid JSON: {$previous->getMessage()}.", previous: $previous);
    }

    public static function invalidFormat(string $path): self
    {
        return new self("The baseline [{$path}] has an unsupported format. Regenerate it with: php artisan domain:check --generate-baseline");
    }
}
