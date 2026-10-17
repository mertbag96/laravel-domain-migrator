<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Exceptions;

use InvalidArgumentException;

final class InvalidConfigurationException extends InvalidArgumentException
{
    public static function notAnArray(): self
    {
        return new self('The [domain-migrator] configuration must be an array.');
    }

    public static function invalidValue(string $key, string $expected): self
    {
        return new self("The [domain-migrator.{$key}] configuration value must be {$expected}.");
    }

    public static function missingPath(string $path): self
    {
        return new self("The scan path [{$path}] does not exist.");
    }
}
