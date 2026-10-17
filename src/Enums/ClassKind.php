<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Enums;

enum ClassKind: string
{
    case Model = 'model';
    case Controller = 'controller';
    case FormRequest = 'form_request';
    case Resource = 'resource';
    case Middleware = 'middleware';
    case Job = 'job';
    case Event = 'event';
    case Listener = 'listener';
    case Observer = 'observer';
    case Policy = 'policy';
    case Notification = 'notification';
    case Mail = 'mail';
    case Command = 'command';
    case Provider = 'provider';
    case Action = 'action';
    case Service = 'service';
    case Data = 'data';
    case Enum = 'enum';
    case Exception = 'exception';
    case Interface = 'interface';
    case Trait = 'trait';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
