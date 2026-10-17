<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Pest;

use Pest\Contracts\Plugins\Bootable;

/**
 * Registers the "domains" arch preset: arch()->preset()->domains().
 */
final class Plugin implements Bootable
{
    public const string PRESET = 'domains';

    public function boot(): void
    {
        pest()->presets()->custom(self::PRESET, static fn (): array => (new DomainsPreset)());
    }
}
