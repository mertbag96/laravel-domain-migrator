<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Data\DomainReadiness;
use MertBag\DomainMigrator\Data\MigrationPlan;
use MertBag\DomainMigrator\Data\MigrationRisk;
use MertBag\DomainMigrator\Data\RiskItem;
use MertBag\DomainMigrator\Enums\ReadinessStatus;
use MertBag\DomainMigrator\Enums\RiskLevel;
use MertBag\DomainMigrator\Enums\RiskType;

/**
 * @param  array<string, array{RiskLevel, int}>  $risks  Title => level and number of items.
 */
function readinessOf(string $domain, array $risks = []): DomainReadiness
{
    return new DomainReadiness(new MigrationPlan(
        domain: $domain,
        namespace: "App\\Domain\\{$domain}",
        moves: [],
        inPlace: [],
        references: [],
        risks: array_map(fn (string $title, array $risk): MigrationRisk => new MigrationRisk(
            RiskType::Factory,
            $risk[0],
            $title,
            'message',
            'fix',
            array_map(fn (int $item): RiskItem => new RiskItem("App\\Models\\M{$item}", 'detail'), range(1, $risk[1])),
        ), array_keys($risks), array_values($risks)),
        skippedFiles: [],
    ));
}

it('derives the status from the most severe risk', function (): void {
    expect(readinessOf('A')->status())->toBe(ReadinessStatus::Ready)
        ->and(readinessOf('B', ['Strings' => [RiskLevel::Review, 3]])->status())->toBe(ReadinessStatus::Ready)
        ->and(readinessOf('C', ['Factories' => [RiskLevel::Breaking, 1]])->status())->toBe(ReadinessStatus::NeedsPreparation)
        ->and(readinessOf('D', ['Factories' => [RiskLevel::Breaking, 1], 'Files' => [RiskLevel::Blocker, 1]])->status())->toBe(ReadinessStatus::Blocked);
});

it('counts what needs a change before the move, without the risks to review', function (): void {
    expect(readinessOf('A', ['Factories' => [RiskLevel::Breaking, 2], 'Files' => [RiskLevel::Blocker, 1], 'Strings' => [RiskLevel::Review, 9]])->preparationItems())->toBe(3);
});

it('sorts from the easiest domain to move to the hardest', function (): void {
    $sorted = DomainReadiness::sorted([
        readinessOf('Blocked', ['Files' => [RiskLevel::Blocker, 1]]),
        readinessOf('TwoRisks', ['Factories' => [RiskLevel::Breaking, 1], 'Queues' => [RiskLevel::Breaking, 1]]),
        readinessOf('OneRisk', ['Factories' => [RiskLevel::Breaking, 4]]),
        readinessOf('Ready'),
        readinessOf('AlsoReady'),
    ]);

    expect(array_map(fn (DomainReadiness $entry): string => $entry->plan->domain, $sorted))
        ->toBe(['AlsoReady', 'Ready', 'OneRisk', 'TwoRisks', 'Blocked']);
});
