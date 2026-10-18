<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Risks;

use MertBag\DomainMigrator\Data\MigrationRisk;
use MertBag\DomainMigrator\Data\PlanContext;
use MertBag\DomainMigrator\Data\RiskItem;
use MertBag\DomainMigrator\Enums\RiskLevel;
use MertBag\DomainMigrator\Enums\RiskType;

/**
 * Broadcast events are named after their class unless they define broadcastAs().
 */
final readonly class BroadcastNameDetector implements RiskDetector
{
    private const array BROADCAST_INTERFACES = [
        'Illuminate\\Contracts\\Broadcasting\\ShouldBroadcast',
        'Illuminate\\Contracts\\Broadcasting\\ShouldBroadcastNow',
    ];

    public function detect(PlanContext $context): array
    {
        $items = [];

        foreach ($context->moves as $move) {
            if (array_intersect($context->project->interfacesOf($move->class), self::BROADCAST_INTERFACES) === []) {
                continue;
            }

            foreach ($context->project->declarers($move->class) as $facts) {
                if ($facts->declares('broadcastAs')) {
                    continue 2;
                }
            }

            $items[] = new RiskItem($move->class, "is broadcast as {$move->class}", $move->file);
        }

        if ($items === []) {
            return [];
        }

        return [new MigrationRisk(
            RiskType::BroadcastName,
            RiskLevel::Breaking,
            'Broadcast event names',
            'Broadcast events are named after their full class name unless they define broadcastAs(). Echo listeners for the old name stop receiving them after the move.',
            sprintf(
                "Before moving, add a broadcastAs() method that returns the current name, such as return '%s'; in %s, so existing listeners keep working. Or update the frontend listeners after the move.",
                $items[0]->class,
                class_basename($items[0]->class),
            ),
            $items,
        )];
    }
}
