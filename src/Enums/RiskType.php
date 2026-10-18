<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Enums;

enum RiskType: string
{
    case Conflict = 'conflict';
    case MorphType = 'morph_type';
    case QueuedPayload = 'queued_payload';
    case DatabaseNotification = 'database_notification';
    case BroadcastName = 'broadcast_name';
    case Factory = 'factory';
    case Policy = 'policy';
    case Discovery = 'discovery';
    case StringReference = 'string_reference';
}
