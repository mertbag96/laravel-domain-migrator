<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

/**
 * Violations that existed when the baseline was generated; only violations outside it fail a check.
 */
final readonly class Baseline
{
    public const int SCHEMA_VERSION = 1;

    /** @var array<string, BaselineEntry> */
    private array $entries;

    /**
     * @param  list<BaselineEntry>  $entries
     */
    public function __construct(array $entries = [])
    {
        $indexed = [];

        foreach ($entries as $entry) {
            $indexed[$entry->key()] = $entry;
        }

        ksort($indexed);

        $this->entries = $indexed;
    }

    /**
     * @param  list<Violation>  $violations
     */
    public static function fromViolations(array $violations): self
    {
        return new self(array_map(fn (Violation $violation): BaselineEntry => $violation->entry(), $violations));
    }

    public function contains(Violation $violation): bool
    {
        return isset($this->entries[$violation->entry()->key()]);
    }

    /**
     * Entries that no longer occur in the given violations.
     *
     * @param  list<Violation>  $violations
     * @return list<BaselineEntry>
     */
    public function fixedEntries(array $violations): array
    {
        $current = array_flip(array_map(fn (Violation $violation): string => $violation->entry()->key(), $violations));

        return array_values(array_filter($this->entries, fn (BaselineEntry $entry): bool => ! isset($current[$entry->key()])));
    }

    /**
     * @return list<BaselineEntry>
     */
    public function entries(): array
    {
        return array_values($this->entries);
    }

    /**
     * @return array{schema: int, violations: list<array{source: string, target: string}>}
     */
    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA_VERSION,
            'violations' => array_map(fn (BaselineEntry $entry): array => $entry->toArray(), $this->entries()),
        ];
    }
}
