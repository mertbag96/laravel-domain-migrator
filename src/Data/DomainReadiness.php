<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Enums\ReadinessStatus;
use MertBag\DomainMigrator\Enums\RiskLevel;

/**
 * How much work moving a domain takes, summarized from its migration plan.
 */
final readonly class DomainReadiness
{
    public function __construct(
        public MigrationPlan $plan,
    ) {}

    public function status(): ReadinessStatus
    {
        return match (true) {
            $this->plan->count(RiskLevel::Blocker) > 0 => ReadinessStatus::Blocked,
            $this->plan->count(RiskLevel::Breaking) > 0 => ReadinessStatus::NeedsPreparation,
            default => ReadinessStatus::Ready,
        };
    }

    /**
     * Classes and places that need a change before the move, across all blocking and breaking risks.
     */
    public function preparationItems(): int
    {
        return array_sum(array_map(
            fn (MigrationRisk $risk): int => $risk->level === RiskLevel::Review ? 0 : count($risk->items),
            $this->plan->risks,
        ));
    }

    /**
     * Sorts from the easiest domain to move to the hardest: by status, then by the preparation work,
     * then by how many files would change.
     *
     * @param  list<self>  $readiness
     * @return list<self>
     */
    public static function sorted(array $readiness): array
    {
        usort($readiness, fn (self $a, self $b): int => $a->sortKey() <=> $b->sortKey());

        return $readiness;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'domain' => $this->plan->domain,
            'namespace' => $this->plan->namespace,
            'status' => $this->status()->value,
            'moves' => count($this->plan->moves),
            'in_place' => count($this->plan->inPlace),
            'referencing_files' => count($this->plan->referencingFiles()),
            'blockers' => $this->plan->count(RiskLevel::Blocker),
            'breaking' => $this->plan->count(RiskLevel::Breaking),
            'review' => $this->plan->count(RiskLevel::Review),
            'preparation_items' => $this->preparationItems(),
            'risks' => array_map(fn (MigrationRisk $risk): array => [
                'type' => $risk->type->value,
                'level' => $risk->level->value,
                'title' => $risk->title,
                'items' => count($risk->items),
            ], $this->plan->risks),
        ];
    }

    /**
     * @return list<int|string>
     */
    private function sortKey(): array
    {
        return [
            $this->status()->order(),
            $this->plan->count(RiskLevel::Blocker) + $this->plan->count(RiskLevel::Breaking),
            $this->preparationItems(),
            count($this->plan->referencingFiles()),
            $this->plan->domain,
        ];
    }
}
