<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Scanning;

use MertBag\DomainMigrator\Data\ClassFacts;
use MertBag\DomainMigrator\Data\ClassReference;
use MertBag\DomainMigrator\Enums\ReferenceKind;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects references to the moved classes, facts about every class-like and morph map registrations.
 *
 * Expects names to be resolved by PhpParser's NameResolver beforehand.
 *
 * @internal
 */
final class ProjectVisitor extends NodeVisitorAbstract
{
    private const array MORPH_RELATION_METHODS = ['morphMany', 'morphOne', 'morphToMany', 'morphedByMany', 'morphTo'];

    private const array MORPH_MAP_METHODS = ['morphMap', 'enforceMorphMap', 'requireMorphMap'];

    private const string RELATION = 'Illuminate\\Database\\Eloquent\\Relations\\Relation';

    /** @var array<string, ClassReference> */
    private array $references = [];

    /** @var list<FactsCollector> */
    private array $stack = [];

    /** @var list<FactsCollector> */
    private array $collected = [];

    /** @var ?list<string> */
    private ?array $morphMap = null;

    /**
     * @param  array<string, true>  $moved  The moved class names, as keys.
     */
    public function __construct(
        private readonly string $file,
        private readonly array $moved,
        private readonly ClassNameMatcher $matcher,
    ) {}

    public function enterNode(Node $node): null
    {
        if ($node instanceof Stmt\Use_ || $node instanceof Stmt\GroupUse) {
            $this->collectImports($node);
        } elseif ($node instanceof Stmt\ClassLike && $node->namespacedName !== null) {
            $this->enterClassLike($node);
        } elseif ($node instanceof Name\FullyQualified) {
            $this->reference($node->toString(), $node->getStartLine(), ReferenceKind::Code);
        } elseif ($node instanceof Scalar\String_) {
            foreach ($this->matcher->match($node->value) as $class) {
                $this->reference($class, $node->getStartLine(), ReferenceKind::String);
            }
        } elseif ($node instanceof Expr\StaticCall) {
            $this->collectMorphMap($node);
        }

        $collector = $this->stack === [] ? null : $this->stack[count($this->stack) - 1];

        if ($collector !== null) {
            $this->collectFacts($node, $collector);
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Stmt\ClassLike && $node->namespacedName !== null) {
            array_pop($this->stack);
        } elseif ($node instanceof Stmt\ClassMethod && $this->stack !== []) {
            $this->stack[count($this->stack) - 1]->currentMethod = null;
        }

        return null;
    }

    /**
     * @return list<ClassReference>
     */
    public function references(): array
    {
        return array_values($this->references);
    }

    /**
     * @return list<ClassFacts>
     */
    public function facts(): array
    {
        return array_map(fn (FactsCollector $collector): ClassFacts => $collector->toFacts($this->file), $this->collected);
    }

    /**
     * @return ?list<string>
     */
    public function morphMap(): ?array
    {
        return $this->morphMap;
    }

    private function reference(string $class, int $line, ReferenceKind $kind): void
    {
        $class = ltrim($class, '\\');

        if (isset($this->moved[$class])) {
            $this->references["{$kind->value}:{$class}:{$line}"] ??= new ClassReference($class, $this->file, $line, $kind);
        }
    }

    private function collectImports(Stmt\Use_|Stmt\GroupUse $node): void
    {
        $prefix = $node instanceof Stmt\GroupUse ? $node->prefix->toString().'\\' : '';

        foreach ($node->uses as $use) {
            $type = $use->type === Stmt\Use_::TYPE_UNKNOWN ? $node->type : $use->type;

            if ($type === Stmt\Use_::TYPE_NORMAL) {
                $this->reference($prefix.$use->name->toString(), $use->getStartLine(), ReferenceKind::Code);
            }
        }
    }

    private function enterClassLike(Stmt\ClassLike $node): void
    {
        $collector = new FactsCollector((string) $node->namespacedName, $node->getStartLine());

        if ($node instanceof Stmt\Class_) {
            $collector->parent = $node->extends?->toString();
            $collector->interfaces = $this->names($node->implements);
        } elseif ($node instanceof Stmt\Interface_) {
            $collector->interfaces = $this->names($node->extends);
        } elseif ($node instanceof Stmt\Enum_) {
            $collector->interfaces = $this->names($node->implements);
        }

        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $collector->attributes[] = $attribute->name->toString();
            }
        }

        $this->stack[] = $collector;
        $this->collected[] = $collector;
    }

    private function collectFacts(Node $node, FactsCollector $collector): void
    {
        if ($node instanceof Stmt\TraitUse) {
            foreach ($node->traits as $trait) {
                $collector->traits[] = $trait->toString();
            }
        } elseif ($node instanceof Stmt\ClassMethod) {
            $collector->methods[] = $node->name->toLowerString();
            $collector->currentMethod = $node->name->toLowerString();
        } elseif ($node instanceof Stmt\Property) {
            $this->collectModelProperty($node, $collector);
        } elseif ($node instanceof Expr\MethodCall) {
            $this->collectRelationCall($node, $collector);
        } elseif ($node instanceof Stmt\Return_ && $collector->currentMethod === 'via' && $node->expr !== null) {
            array_push($collector->channels, ...$this->channelsIn($node->expr));
        }
    }

    private function collectModelProperty(Stmt\Property $node, FactsCollector $collector): void
    {
        foreach ($node->props as $property) {
            if ($property->name->toString() === 'model' && $property->default !== null) {
                $collector->modelProperty = $this->classConstant($property->default);
            }
        }
    }

    private function collectRelationCall(Expr\MethodCall $node, FactsCollector $collector): void
    {
        if (! $node->var instanceof Expr\Variable || $node->var->name !== 'this' || ! $node->name instanceof Node\Identifier) {
            return;
        }

        $method = $node->name->toString();

        if (! in_array($method, self::MORPH_RELATION_METHODS, true)) {
            return;
        }

        $collector->relationCalls[$method] ??= $node->getStartLine();

        if ($method === 'morphedByMany' && isset($node->getArgs()[0])) {
            $related = $this->classConstant($node->getArgs()[0]->value);

            if ($related !== null) {
                $collector->morphedBy[] = $related;
            }
        }
    }

    private function collectMorphMap(Expr\StaticCall $node): void
    {
        if (
            ! $node->class instanceof Name
            || $node->class->toString() !== self::RELATION
            || ! $node->name instanceof Node\Identifier
            || ! in_array($node->name->toString(), self::MORPH_MAP_METHODS, true)
        ) {
            return;
        }

        $this->morphMap ??= [];

        foreach ($node->getArgs() as $argument) {
            if ($argument->value instanceof Expr\Array_) {
                foreach ($argument->value->items as $item) {
                    $class = $this->classConstant($item->value);

                    if ($class !== null) {
                        $this->morphMap[] = $class;
                    }
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function channelsIn(Expr $expression): array
    {
        $items = $expression instanceof Expr\Array_ ? array_map(fn (Node\ArrayItem $item): Expr => $item->value, $expression->items) : [$expression];
        $channels = [];

        foreach ($items as $item) {
            $channel = $item instanceof Scalar\String_ ? $item->value : $this->classConstant($item);

            if ($channel !== null) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }

    /**
     * @param  array<Name>  $names
     * @return list<string>
     */
    private function names(array $names): array
    {
        return array_values(array_map(fn (Name $name): string => $name->toString(), $names));
    }

    private function classConstant(Expr $expression): ?string
    {
        if (
            $expression instanceof Expr\ClassConstFetch
            && $expression->class instanceof Name
            && $expression->name instanceof Node\Identifier
            && $expression->name->toLowerString() === 'class'
            && ! $expression->class->isSpecialClassName()
        ) {
            return $expression->class->toString();
        }

        return null;
    }
}
