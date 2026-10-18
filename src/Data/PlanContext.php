<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Analysis\Graph\DependencyGraph;
use MertBag\DomainMigrator\Enums\ClassKind;
use MertBag\DomainMigrator\Enums\ReferenceKind;

/**
 * Everything a risk detector needs to know about a planned move.
 */
final readonly class PlanContext
{
    /** @var array<string, true> */
    private array $targets;

    /**
     * @param  array<string, ClassMove>  $moves  Keyed by the current class name.
     * @param  list<string>  $unresolved  Domain classes that are not under a PSR-4 root, so no target could be found.
     * @param  list<Psr4Root>  $roots
     */
    public function __construct(
        public string $domain,
        public string $basePath,
        public array $moves,
        public array $unresolved,
        public array $roots,
        public DependencyGraph $graph,
        public ProjectScan $project,
    ) {
        $this->targets = array_fill_keys(array_map(fn (ClassMove $move): string => $move->target, array_values($moves)), true);
    }

    public function isMoved(string $class): bool
    {
        return isset($this->moves[$class]);
    }

    /**
     * The class name after the move.
     */
    public function renamed(string $class): string
    {
        return isset($this->moves[$class]) ? $this->moves[$class]->target : $class;
    }

    public function kindOf(string $class): ?ClassKind
    {
        return isset($this->graph->nodes[$class]) ? $this->graph->nodes[$class]->kind : null;
    }

    /**
     * Whether the class is declared in the project before the move.
     */
    public function exists(string $class): bool
    {
        return isset($this->graph->nodes[$class]) || $this->project->facts($class) !== null;
    }

    /**
     * Whether the class is declared in the project after the move.
     */
    public function existsAfterMove(string $class): bool
    {
        return isset($this->targets[$class]) || (! $this->isMoved($class) && $this->exists($class));
    }

    /**
     * Whether a file under one of the given path prefixes refers to the class in code.
     *
     * @param  list<string>  $prefixes  e.g. app/Providers/, bootstrap/
     */
    public function isReferencedFrom(string $class, array $prefixes): bool
    {
        foreach ($this->project->referencesTo($class) as $reference) {
            if ($reference->kind !== ReferenceKind::Code) {
                continue;
            }

            foreach ($prefixes as $prefix) {
                if (str_starts_with($reference->file, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
