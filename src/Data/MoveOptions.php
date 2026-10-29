<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;

final readonly class MoveOptions
{
    public function __construct(
        public bool $backup = true,
        public string $backupPath = '.domain-migrator/backups',
    ) {}

    public static function fromConfig(mixed $config): self
    {
        if (! is_array($config)) {
            throw InvalidConfigurationException::notAnArray();
        }

        $move = $config['move'] ?? [];

        if (! is_array($move)) {
            throw InvalidConfigurationException::invalidValue('move', 'an array');
        }

        $backup = $move['backup'] ?? true;

        if (! is_bool($backup)) {
            throw InvalidConfigurationException::invalidValue('move.backup', 'true or false');
        }

        $backupPath = $move['backup_path'] ?? '.domain-migrator/backups';

        if (! is_string($backupPath) || $backupPath === '' || str_contains($backupPath, '..')) {
            throw InvalidConfigurationException::invalidValue('move.backup_path', 'a relative directory without ..');
        }

        return new self($backup, trim(str_replace('\\', '/', $backupPath), '/'));
    }
}
