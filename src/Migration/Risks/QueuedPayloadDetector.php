<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Risks;

use MertBag\DomainMigrator\Data\MigrationRisk;
use MertBag\DomainMigrator\Data\PlanContext;
use MertBag\DomainMigrator\Data\RiskItem;
use MertBag\DomainMigrator\Enums\ClassKind;
use MertBag\DomainMigrator\Enums\RiskLevel;
use MertBag\DomainMigrator\Enums\RiskType;

/**
 * Queued classes, and the models they carry, are serialized with their class names.
 */
final readonly class QueuedPayloadDetector implements RiskDetector
{
    private const array QUEUED_INTERFACES = [
        'Illuminate\\Contracts\\Queue\\ShouldQueue',
        'Illuminate\\Contracts\\Queue\\ShouldQueueAfterCommit',
        'Illuminate\\Contracts\\Broadcasting\\ShouldBroadcast',
    ];

    private const string BROADCAST_NOW = 'Illuminate\\Contracts\\Broadcasting\\ShouldBroadcastNow';

    private const int LISTED_CARRIERS = 3;

    public function detect(PlanContext $context): array
    {
        $items = [];

        foreach ($context->moves as $move) {
            $interface = $this->queuedInterface($context, $move->class);

            if ($interface !== null) {
                $items[] = new RiskItem($move->class, 'implements '.class_basename($interface), $move->file);
            }
        }

        foreach ($context->moves as $move) {
            if ($move->kind !== ClassKind::Model) {
                continue;
            }

            $carriers = array_values(array_filter(
                $context->graph->dependentsOf($move->class),
                fn (string $dependent): bool => $this->queuedInterface($context, $dependent) !== null,
            ));

            if ($carriers !== []) {
                $items[] = new RiskItem($move->class, 'is used by queued '.$this->list($carriers), $move->file);
            }
        }

        if ($items === []) {
            return [];
        }

        return [new MigrationRisk(
            RiskType::QueuedPayload,
            RiskLevel::Breaking,
            'Queued payloads',
            'Queued jobs, listeners, notifications, mail and broadcast events are stored with their class names, and so are the models they carry. Payloads already in the queue or in failed_jobs fail to unserialize after the move.',
            'Deploy the move when the queues are empty and failed_jobs is cleared, or keep class_alias() entries for the old names until the remaining payloads are processed.',
            $items,
        )];
    }

    private function queuedInterface(PlanContext $context, string $class): ?string
    {
        $interfaces = $context->project->interfacesOf($class);

        if (in_array(self::BROADCAST_NOW, $interfaces, true)) {
            return null;
        }

        foreach (self::QUEUED_INTERFACES as $interface) {
            if (in_array($interface, $interfaces, true)) {
                return $interface;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $classes
     */
    private function list(array $classes): string
    {
        $names = array_map(class_basename(...), array_slice($classes, 0, self::LISTED_CARRIERS));
        $more = count($classes) - self::LISTED_CARRIERS;

        return implode(', ', $names).($more > 0 ? " and {$more} more" : '');
    }
}
