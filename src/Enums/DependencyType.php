<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Enums;

enum DependencyType: string
{
    case Relationship = 'relationship';
    case Inheritance = 'inheritance';
    case FrameworkBinding = 'framework_binding';
    case Usage = 'usage';
    case Import = 'import';
}
