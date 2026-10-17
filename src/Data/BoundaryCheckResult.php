<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

final readonly class BoundaryCheckResult
{
    public const int SCHEMA_VERSION = 1;

    /**
     * @param  list<Violation>  $violations  Violations that are not in the baseline.
     * @param  list<Violation>  $baselined  Violations covered by the baseline.
     * @param  list<BaselineEntry>  $fixedEntries  Baseline entries that no longer occur.
     * @param  list<string>  $uncheckedDomains  Domains without depends_on.
     * @param  list<string>  $unmappedClasses  Scanned classes that are not in the domain map.
     * @param  list<SkippedFile>  $skippedFiles
     */
    public function __construct(
        public array $violations,
        public array $baselined,
        public array $fixedEntries,
        public array $uncheckedDomains,
        public array $unmappedClasses,
        public array $skippedFiles,
        public int $classCount,
    ) {}

    public function passed(): bool
    {
        return $this->violations === [];
    }

    /**
     * Every current violation, for writing a new baseline.
     *
     * @return list<Violation>
     */
    public function allViolations(): array
    {
        return [...$this->violations, ...$this->baselined];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA_VERSION,
            'passed' => $this->passed(),
            'summary' => [
                'classes' => $this->classCount,
                'violations' => count($this->violations),
                'baselined' => count($this->baselined),
                'fixed_baseline_entries' => count($this->fixedEntries),
                'unchecked_domains' => count($this->uncheckedDomains),
                'unmapped_classes' => count($this->unmappedClasses),
                'skipped_files' => count($this->skippedFiles),
            ],
            'violations' => array_map(fn (Violation $violation): array => $violation->toArray(), $this->violations),
            'fixed_baseline_entries' => array_map(fn (BaselineEntry $entry): array => $entry->toArray(), $this->fixedEntries),
            'unchecked_domains' => $this->uncheckedDomains,
            'unmapped_classes' => $this->unmappedClasses,
            'skipped_files' => array_map(fn (SkippedFile $file): array => ['file' => $file->file, 'reason' => $file->reason], $this->skippedFiles),
        ];
    }
}
