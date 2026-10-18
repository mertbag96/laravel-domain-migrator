<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Enums;

enum ReferenceKind: string
{
    /** An import, type or ::class reference that a rename updates. */
    case Code = 'code';

    /** A class name written in a string, a Blade template or a data file. */
    case String = 'string';
}
