<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Exceptions;

use InvalidArgumentException;

final class InvalidMigrationPlanException extends InvalidArgumentException
{
    /**
     * @param  list<string>  $available
     */
    public static function unknownDomain(string $domain, array $available): self
    {
        return new self(sprintf(
            'The domain [%s] is not in the domain map. Available domains: %s.',
            $domain,
            $available === [] ? 'none' : implode(', ', $available),
        ));
    }

    public static function invalidComposerJson(string $path): self
    {
        return new self("The autoload section of [{$path}] could not be read.");
    }
}
