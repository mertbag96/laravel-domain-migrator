<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

/**
 * What a class-like declares, as far as the Laravel conventions that a move can break are concerned.
 */
final readonly class ClassFacts
{
    /**
     * @param  list<string>  $interfaces
     * @param  list<string>  $traits
     * @param  list<string>  $methods  Declared method names, lowercased.
     * @param  list<string>  $attributes  Attribute class names.
     * @param  array<string, int>  $relationCalls  Line of the first $this->method() call, for morph relation methods.
     * @param  list<string>  $morphedBy  Classes passed to $this->morphedByMany().
     * @param  list<string>  $channels  Strings and classes returned from via(), for notifications.
     * @param  ?string  $modelProperty  The class assigned to a $model property, for factories.
     */
    public function __construct(
        public string $name,
        public string $file,
        public int $line,
        public ?string $parent = null,
        public array $interfaces = [],
        public array $traits = [],
        public array $methods = [],
        public array $attributes = [],
        public array $relationCalls = [],
        public array $morphedBy = [],
        public array $channels = [],
        public ?string $modelProperty = null,
    ) {}

    public function declares(string $method): bool
    {
        return in_array(strtolower($method), $this->methods, true);
    }
}
