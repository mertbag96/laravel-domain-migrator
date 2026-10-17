<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Analysis\Scanning;

use MertBag\DomainMigrator\Data\ScannedClass;
use MertBag\DomainMigrator\Enums\DeclarationType;
use MertBag\DomainMigrator\Enums\DependencyType;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects the dependencies of every named class-like declared in a file.
 *
 * Expects names to be resolved by PhpParser's NameResolver beforehand.
 *
 * @internal
 */
final class DependencyVisitor extends NodeVisitorAbstract
{
    private const array RELATIONSHIP_METHODS = [
        'belongsTo',
        'belongsToMany',
        'hasMany',
        'hasManyThrough',
        'hasOne',
        'hasOneThrough',
        'morphMany',
        'morphOne',
        'morphToMany',
        'morphedByMany',
    ];

    /** @var array<string, int> */
    private array $imports = [];

    /** @var list<ClassCollector> */
    private array $stack = [];

    /** @var list<ClassCollector> */
    private array $collected = [];

    public function enterNode(Node $node): null
    {
        if ($node instanceof Stmt\Use_ || $node instanceof Stmt\GroupUse) {
            $this->collectImports($node);

            return null;
        }

        if ($node instanceof Stmt\ClassLike && $node->namespacedName !== null) {
            $this->enterClassLike($node, $node->namespacedName->toString());

            return null;
        }

        if ($this->stack !== []) {
            $this->collectDependencies($node, $this->stack[count($this->stack) - 1]);
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Stmt\ClassLike && $node->namespacedName !== null) {
            array_pop($this->stack);
        }

        return null;
    }

    /**
     * @return list<ScannedClass>
     */
    public function result(string $file): array
    {
        return array_map(
            fn (ClassCollector $collector): ScannedClass => $collector->toScannedClass($file, $this->imports),
            $this->collected,
        );
    }

    private function collectImports(Stmt\Use_|Stmt\GroupUse $node): void
    {
        $prefix = $node instanceof Stmt\GroupUse ? $node->prefix->toString().'\\' : '';

        foreach ($node->uses as $use) {
            $type = $use->type === Stmt\Use_::TYPE_UNKNOWN ? $node->type : $use->type;

            if ($type === Stmt\Use_::TYPE_NORMAL) {
                $this->imports[$prefix.$use->name->toString()] ??= $use->getStartLine();
            }
        }
    }

    private function enterClassLike(Stmt\ClassLike $node, string $name): void
    {
        $collector = new ClassCollector($name, DeclarationType::fromNode($node));

        if ($node instanceof Stmt\Class_) {
            $collector->parent = $node->extends === null ? null : $this->nameOf($node->extends);
            $collector->interfaces = $this->namesOf($node->implements);
        } elseif ($node instanceof Stmt\Interface_) {
            $collector->interfaces = $this->namesOf($node->extends);
        } elseif ($node instanceof Stmt\Enum_) {
            $collector->interfaces = $this->namesOf($node->implements);
        }

        $collector->add($collector->parent, DependencyType::Inheritance, $node->getStartLine());

        foreach ($collector->interfaces as $interface) {
            $collector->add($interface, DependencyType::Inheritance, $node->getStartLine());
        }

        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $collector->add($this->nameOf($attribute->name), DependencyType::Usage, $attribute->getStartLine());

                foreach ($attribute->args as $argument) {
                    foreach ($this->classConstantsIn($argument->value) as $target) {
                        $collector->add($target, DependencyType::FrameworkBinding, $attribute->getStartLine());
                    }
                }
            }
        }

        $this->stack[] = $collector;
        $this->collected[] = $collector;
    }

    private function collectDependencies(Node $node, ClassCollector $collector): void
    {
        if ($node instanceof Stmt\TraitUse) {
            foreach ($this->namesOf($node->traits) as $trait) {
                $collector->add($trait, DependencyType::Inheritance, $node->getStartLine());
            }
        } elseif (
            $node instanceof Expr\New_
            || $node instanceof Expr\StaticCall
            || $node instanceof Expr\StaticPropertyFetch
            || $node instanceof Expr\ClassConstFetch
            || $node instanceof Expr\Instanceof_
        ) {
            if ($node->class instanceof Name) {
                $collector->add($this->nameOf($node->class), DependencyType::Usage, $node->getStartLine());
            }
        } elseif ($node instanceof Stmt\Catch_) {
            foreach ($this->namesOf($node->types) as $type) {
                $collector->add($type, DependencyType::Usage, $node->getStartLine());
            }
        } elseif ($node instanceof Node\Param || $node instanceof Stmt\Property) {
            foreach ($this->typeNames($node->type) as $type) {
                $collector->add($type, DependencyType::Usage, $node->getStartLine());
            }
        } elseif ($node instanceof Expr\MethodCall) {
            $this->collectRelationship($node, $collector);
        }

        if ($node instanceof Node\FunctionLike) {
            foreach ($this->typeNames($node->getReturnType()) as $type) {
                $collector->add($type, DependencyType::Usage, $node->getStartLine());
            }
        }

        if ($node instanceof Stmt\ClassMethod && $this->isHandler($node->name->toString())) {
            foreach ($this->typeNames($node->params[0]->type ?? null) as $type) {
                $collector->handlerParameters[] = $type;
            }
        }
    }

    private function collectRelationship(Expr\MethodCall $node, ClassCollector $collector): void
    {
        if (! $node->var instanceof Expr\Variable || $node->var->name !== 'this') {
            return;
        }

        if (! $node->name instanceof Node\Identifier || ! in_array($node->name->toString(), self::RELATIONSHIP_METHODS, true)) {
            return;
        }

        foreach ($node->getArgs() as $argument) {
            foreach ($this->classConstantsIn($argument->value) as $target) {
                $collector->add($target, DependencyType::Relationship, $node->getStartLine());
            }
        }
    }

    /**
     * Class names referenced as Foo::class, directly or inside an array literal.
     *
     * @return list<string>
     */
    private function classConstantsIn(Expr $expression): array
    {
        if (
            $expression instanceof Expr\ClassConstFetch
            && $expression->class instanceof Name
            && $expression->name instanceof Node\Identifier
            && $expression->name->toLowerString() === 'class'
        ) {
            $name = $this->nameOf($expression->class);

            return $name === null ? [] : [$name];
        }

        if (! $expression instanceof Expr\Array_) {
            return [];
        }

        $names = [];

        foreach ($expression->items as $item) {
            array_push($names, ...$this->classConstantsIn($item->value));
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function typeNames(?Node $type): array
    {
        if ($type instanceof Name) {
            $name = $this->nameOf($type);

            return $name === null ? [] : [$name];
        }

        if ($type instanceof Node\NullableType) {
            return $this->typeNames($type->type);
        }

        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            return array_merge(...array_map($this->typeNames(...), $type->types));
        }

        return [];
    }

    /**
     * @param  array<Name>  $names
     * @return list<string>
     */
    private function namesOf(array $names): array
    {
        return array_values(array_filter(array_map($this->nameOf(...), $names)));
    }

    private function nameOf(Name $name): ?string
    {
        return $name->isSpecialClassName() ? null : $name->toString();
    }

    private function isHandler(string $method): bool
    {
        return $method === '__invoke' || str_starts_with($method, 'handle');
    }
}
