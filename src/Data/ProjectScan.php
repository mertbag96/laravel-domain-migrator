<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

/**
 * References to the moved classes and facts about every class, from the application code, configuration,
 * routes, database files, views and tests.
 */
final readonly class ProjectScan
{
    /** @var array<string, list<ClassReference>> */
    private array $referencesByClass;

    /**
     * @param  list<ClassReference>  $references
     * @param  array<string, ClassFacts>  $facts  Keyed by fully qualified class name.
     * @param  ?list<string>  $morphMap  Classes registered with Relation::morphMap() and its variants; null when there is none.
     * @param  list<SkippedFile>  $skippedFiles
     */
    public function __construct(
        public array $references = [],
        public array $facts = [],
        public ?array $morphMap = null,
        public array $skippedFiles = [],
    ) {
        $byClass = [];

        foreach ($references as $reference) {
            $byClass[$reference->class][] = $reference;
        }

        $this->referencesByClass = $byClass;
    }

    /**
     * The same scan, with only the references to the given classes.
     *
     * @param  list<string>  $classes
     */
    public function withReferencesTo(array $classes): self
    {
        $references = array_merge([], ...array_map(fn (string $class): array => $this->referencesByClass[$class] ?? [], $classes));

        return new self($references, $this->facts, $this->morphMap, $this->skippedFiles);
    }

    public function facts(string $class): ?ClassFacts
    {
        return $this->facts[$class] ?? null;
    }

    /**
     * The class and its parents that are declared in the project, nearest first.
     *
     * @return list<ClassFacts>
     */
    public function lineage(string $class): array
    {
        $lineage = [];

        while (($facts = $this->facts[$class] ?? null) !== null && ! isset($lineage[$facts->name])) {
            $lineage[$facts->name] = $facts;

            if ($facts->parent === null) {
                break;
            }

            $class = $facts->parent;
        }

        return array_values($lineage);
    }

    /**
     * Every ancestor class name, including the first one outside the project.
     *
     * @return list<string>
     */
    public function ancestors(string $class): array
    {
        return array_values(array_filter(array_map(
            fn (ClassFacts $facts): ?string => $facts->parent,
            $this->lineage($class),
        )));
    }

    /**
     * Interfaces implemented by the class or one of its project parents.
     *
     * @return list<string>
     */
    public function interfacesOf(string $class): array
    {
        return array_values(array_unique(array_merge([], ...array_map(
            fn (ClassFacts $facts): array => $facts->interfaces,
            $this->lineage($class),
        ))));
    }

    /**
     * The class, its project parents and the project traits they use, recursively: everything that can
     * declare methods and relations for the class.
     *
     * @return list<ClassFacts>
     */
    public function declarers(string $class): array
    {
        $declarers = [];
        $pending = array_map(fn (ClassFacts $facts): string => $facts->name, $this->lineage($class));

        while (($name = array_shift($pending)) !== null) {
            $facts = $this->facts[$name] ?? null;

            if ($facts === null || isset($declarers[$name])) {
                continue;
            }

            $declarers[$name] = $facts;
            array_push($pending, ...$facts->traits);
        }

        return array_values($declarers);
    }

    /**
     * Traits used by the class, its project parents or the project traits they use.
     *
     * @return list<string>
     */
    public function traitsOf(string $class): array
    {
        return array_values(array_unique(array_merge([], ...array_map(
            fn (ClassFacts $facts): array => $facts->traits,
            $this->declarers($class),
        ))));
    }

    /**
     * @return list<ClassReference>
     */
    public function referencesTo(string $class): array
    {
        return $this->referencesByClass[$class] ?? [];
    }
}
