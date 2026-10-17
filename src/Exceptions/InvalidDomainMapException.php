<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Exceptions;

use InvalidArgumentException;
use JsonException;

final class InvalidDomainMapException extends InvalidArgumentException
{
    public static function notFound(string $path): self
    {
        return new self("The domain map [{$path}] does not exist. Create it with: php artisan domain:analyze --map={$path}");
    }

    public static function invalidJson(string $path, JsonException $previous): self
    {
        return new self("The domain map [{$path}] is not valid JSON: {$previous->getMessage()}.", previous: $previous);
    }

    public static function unsupportedSchema(string $path): self
    {
        return new self("The domain map [{$path}] has an unsupported schema. Supported schemas are 1 and 2.");
    }

    public static function invalidValue(string $path, string $key, string $expected): self
    {
        return new self("The domain map [{$path}] is invalid: [{$key}] must be {$expected}.");
    }

    public static function duplicateDomain(string $path, string $domain): self
    {
        return new self("The domain map [{$path}] defines the domain [{$domain}] more than once.");
    }

    public static function duplicateClass(string $path, string $class): self
    {
        return new self("The domain map [{$path}] lists [{$class}] more than once. Each class can belong to one domain only.");
    }

    public static function unknownDependency(string $path, string $domain, string $dependency): self
    {
        return new self("The domain map [{$path}] is invalid: [{$domain}] depends on [{$dependency}], which is not a domain in the map.");
    }
}
