<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Rewriting;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects what a rewrite needs from the unresolved syntax tree: the namespace, the imports and the strings.
 *
 * @internal
 */
final class RewriteCollector extends NodeVisitorAbstract
{
    public ?Stmt\Namespace_ $namespace = null;

    /** @var array<string, string> Lower-case alias => imported class. */
    public array $imports = [];

    /** @var list<array{Name, string, bool}> Name node, imported class and whether it is part of a grouped import. */
    public array $useItems = [];

    public ?Stmt $lastImport = null;

    /** @var array<string, true> Lower-case short names of the classes declared in the file. */
    public array $declared = [];

    /** @var list<Scalar\String_> */
    public array $strings = [];

    public function enterNode(Node $node): null
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->namespace ??= $node;
        } elseif ($node instanceof Stmt\Use_ || $node instanceof Stmt\GroupUse) {
            $this->collectImports($node);
        } elseif ($node instanceof Stmt\ClassLike && $node->name !== null) {
            $this->declared[$node->name->toLowerString()] = true;
        } elseif ($node instanceof Scalar\String_) {
            $this->strings[] = $node;
        }

        return null;
    }

    private function collectImports(Stmt\Use_|Stmt\GroupUse $node): void
    {
        $this->lastImport = $node;
        $grouped = $node instanceof Stmt\GroupUse;
        $prefix = $grouped ? $node->prefix->toString().'\\' : '';

        foreach ($node->uses as $use) {
            $type = $use->type === Stmt\Use_::TYPE_UNKNOWN ? $node->type : $use->type;

            if ($type !== Stmt\Use_::TYPE_NORMAL) {
                continue;
            }

            $class = $prefix.$use->name->toString();
            $this->imports[$use->getAlias()->toLowerString()] = $class;
            $this->useItems[] = [$use->name, $class, $grouped];
        }
    }
}
