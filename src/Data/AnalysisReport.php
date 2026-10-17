<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Analysis\Graph\DependencyGraph;
use MertBag\DomainMigrator\Enums\ClassKind;
use MertBag\DomainMigrator\Enums\DependencyType;

final readonly class AnalysisReport
{
    public const int SCHEMA_VERSION = 1;

    /**
     * @param  array<string, ClassMetrics>  $metrics  Keyed by fully qualified class name.
     * @param  list<SkippedFile>  $skippedFiles
     */
    public function __construct(
        public DependencyGraph $graph,
        public array $metrics,
        public array $skippedFiles,
        public int $hotspotLimit,
        public DomainMap $domains = new DomainMap,
    ) {}

    /**
     * Classes with the most dependents, which are the hardest to move between domains.
     *
     * @return list<ClassMetrics>
     */
    public function hotspots(): array
    {
        $candidates = array_values(array_filter(
            $this->metrics,
            fn (ClassMetrics $metrics): bool => $metrics->afferent > 0,
        ));

        usort($candidates, fn (ClassMetrics $a, ClassMetrics $b): int => [$b->afferent, $b->efferent, $a->class]
            <=> [$a->afferent, $a->efferent, $b->class]);

        return array_slice($candidates, 0, $this->hotspotLimit);
    }

    /**
     * @return list<array{kind: ClassKind, count: int}>
     */
    public function kindCounts(): array
    {
        $counts = [];

        foreach ($this->graph->nodes as $node) {
            $counts[$node->kind->value] = ($counts[$node->kind->value] ?? 0) + 1;
        }

        $result = [];

        foreach (ClassKind::cases() as $kind) {
            if (isset($counts[$kind->value])) {
                $result[] = ['kind' => $kind, 'count' => $counts[$kind->value]];
            }
        }

        return $result;
    }

    public function isolatedClassCount(): int
    {
        return count(array_filter($this->metrics, fn (ClassMetrics $metrics): bool => $metrics->isIsolated()));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA_VERSION,
            'summary' => [
                'classes' => count($this->graph->nodes),
                'dependencies' => count($this->graph->edges),
                'isolated_classes' => $this->isolatedClassCount(),
                'domains' => count($this->domains->domains),
                'shared_classes' => count($this->domains->shared),
                'skipped_files' => count($this->skippedFiles),
            ],
            'kinds' => array_column(
                array_map(fn (array $entry): array => [$entry['kind']->value, $entry['count']], $this->kindCounts()),
                1,
                0,
            ),
            'classes' => array_values(array_map(fn (ClassNode $node): array => [
                'name' => $node->name,
                'kind' => $node->kind->value,
                'declaration' => $node->declaration->value,
                'file' => $node->file,
                'domain' => $this->domains->domainOf($node->name),
                'dependents' => $this->metrics[$node->name]->afferent,
                'dependencies' => $this->metrics[$node->name]->efferent,
                'instability' => $this->metrics[$node->name]->instability,
            ], $this->graph->nodes)),
            'dependencies' => array_map(fn (Dependency $dependency): array => [
                'source' => $dependency->source,
                'target' => $dependency->target,
                'types' => array_map(fn (DependencyType $type): string => $type->value, $dependency->types),
                'weight' => $dependency->weight,
            ], $this->graph->edges),
            'domains' => $this->domains->toArray(),
            'hotspots' => array_map(fn (ClassMetrics $metrics): string => $metrics->class, $this->hotspots()),
            'skipped_files' => array_map(fn (SkippedFile $file): array => [
                'file' => $file->file,
                'reason' => $file->reason,
            ], $this->skippedFiles),
        ];
    }
}
