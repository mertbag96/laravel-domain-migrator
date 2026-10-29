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

    public static function blocked(string $domain, int $blockers): self
    {
        return new self(sprintf(
            'The domain [%s] cannot be moved: %d blocker%s. Run domain:plan %s to see what to fix first.',
            $domain,
            $blockers,
            $blockers === 1 ? '' : 's',
            $domain,
        ));
    }

    public static function breaking(string $domain, int $risks): self
    {
        return new self(sprintf(
            'Moving the domain [%s] breaks %d thing%s at runtime unless handled. Run domain:plan %s to review them, then repeat with --force to move anyway.',
            $domain,
            $risks,
            $risks === 1 ? '' : 's',
            $domain,
        ));
    }

    public static function targetExists(string $file): self
    {
        return new self("Cannot move: [{$file}] already exists.");
    }

    public static function unparsable(string $file, string $reason): self
    {
        return new self("Cannot move [{$file}]: it could not be parsed ({$reason}).");
    }

    public static function invalidComposerJson(string $path): self
    {
        return new self("The autoload section of [{$path}] could not be read.");
    }
}
