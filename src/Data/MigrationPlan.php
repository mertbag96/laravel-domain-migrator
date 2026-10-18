<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Enums\ReferenceKind;
use MertBag\DomainMigrator\Enums\RiskLevel;

final readonly class MigrationPlan
{
    public const int SCHEMA_VERSION = 1;

    /**
     * @param  string  $namespace  The namespace the domain moves to, e.g. App\Domain\Orders.
     * @param  list<ClassMove>  $moves
     * @param  list<string>  $inPlace  Domain classes that already live in the target namespace.
     * @param  list<ClassReference>  $references
     * @param  list<MigrationRisk>  $risks  Ordered by level, blockers first.
     * @param  list<SkippedFile>  $skippedFiles
     */
    public function __construct(
        public string $domain,
        public string $namespace,
        public array $moves,
        public array $inPlace,
        public array $references,
        public array $risks,
        public array $skippedFiles = [],
    ) {}

    public function count(RiskLevel $level): int
    {
        return count(array_filter($this->risks, fn (MigrationRisk $risk): bool => $risk->level === $level));
    }

    /**
     * Files with a code reference to a moved class, which the move updates.
     *
     * @return list<string>
     */
    public function referencingFiles(): array
    {
        $files = [];

        foreach ($this->references as $reference) {
            if ($reference->kind === ReferenceKind::Code) {
                $files[$reference->file] = true;
            }
        }

        ksort($files);

        return array_keys($files);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA_VERSION,
            'domain' => $this->domain,
            'namespace' => $this->namespace,
            'summary' => [
                'moves' => count($this->moves),
                'in_place' => count($this->inPlace),
                'referencing_files' => count($this->referencingFiles()),
                'blockers' => $this->count(RiskLevel::Blocker),
                'breaking' => $this->count(RiskLevel::Breaking),
                'review' => $this->count(RiskLevel::Review),
            ],
            'moves' => array_map(fn (ClassMove $move): array => $move->toArray(), $this->moves),
            'in_place' => $this->inPlace,
            'risks' => array_map(fn (MigrationRisk $risk): array => $risk->toArray(), $this->risks),
            'references' => array_map(fn (ClassReference $reference): array => $reference->toArray(), $this->references),
            'skipped_files' => array_map(fn (SkippedFile $file): array => ['file' => $file->file, 'reason' => $file->reason], $this->skippedFiles),
        ];
    }
}
