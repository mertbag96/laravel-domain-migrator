<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Enums;

use PhpParser\Node\Stmt;

enum DeclarationType: string
{
    case Class_ = 'class';
    case Interface = 'interface';
    case Trait = 'trait';
    case Enum = 'enum';

    public static function fromNode(Stmt\ClassLike $node): self
    {
        return match (true) {
            $node instanceof Stmt\Interface_ => self::Interface,
            $node instanceof Stmt\Trait_ => self::Trait,
            $node instanceof Stmt\Enum_ => self::Enum,
            default => self::Class_,
        };
    }
}
