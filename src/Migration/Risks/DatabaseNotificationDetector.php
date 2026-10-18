<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Risks;

use MertBag\DomainMigrator\Data\MigrationRisk;
use MertBag\DomainMigrator\Data\PlanContext;
use MertBag\DomainMigrator\Data\RiskItem;
use MertBag\DomainMigrator\Enums\RiskLevel;
use MertBag\DomainMigrator\Enums\RiskType;

/**
 * Notifications sent through the database channel store their class name in notifications.type.
 */
final readonly class DatabaseNotificationDetector implements RiskDetector
{
    private const array DATABASE_CHANNELS = ['database', 'Illuminate\\Notifications\\Channels\\DatabaseChannel'];

    public function detect(PlanContext $context): array
    {
        $items = [];

        foreach ($context->moves as $move) {
            $declarers = $context->project->declarers($move->class);
            $channels = array_merge([], ...array_map(fn ($facts): array => $facts->channels, $declarers));

            if (array_intersect($channels, self::DATABASE_CHANNELS) === []) {
                continue;
            }

            foreach ($declarers as $facts) {
                if ($facts->declares('databaseType')) {
                    continue 2;
                }
            }

            $items[] = new RiskItem($move->class, 'is sent through the database channel', $move->file);
        }

        if ($items === []) {
            return [];
        }

        return [new MigrationRisk(
            RiskType::DatabaseNotification,
            RiskLevel::Breaking,
            'Database notifications',
            'Database notifications store their class name in notifications.type. Existing notifications keep the old name, which breaks code that filters or renders them by type.',
            sprintf(
                "Before moving, add a databaseType() method that returns the current class name, such as return '%s'; in %s. Or update notifications.type in a migration.",
                $items[0]->class,
                class_basename($items[0]->class),
            ),
            $items,
        )];
    }
}
