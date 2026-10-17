<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

use MertBag\DomainMigrator\Enums\DependencyType;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;

final readonly class DependencyWeights
{
    public function __construct(
        public int $relationship = 5,
        public int $inheritance = 4,
        public int $frameworkBinding = 3,
        public int $usage = 2,
        public int $import = 1,
    ) {}

    public static function fromConfig(mixed $weights): self
    {
        if (! is_array($weights)) {
            throw InvalidConfigurationException::invalidValue('weights', 'an array');
        }

        $defaults = new self;

        return new self(
            relationship: self::weight($weights, DependencyType::Relationship, $defaults->relationship),
            inheritance: self::weight($weights, DependencyType::Inheritance, $defaults->inheritance),
            frameworkBinding: self::weight($weights, DependencyType::FrameworkBinding, $defaults->frameworkBinding),
            usage: self::weight($weights, DependencyType::Usage, $defaults->usage),
            import: self::weight($weights, DependencyType::Import, $defaults->import),
        );
    }

    public function of(DependencyType $type): int
    {
        return match ($type) {
            DependencyType::Relationship => $this->relationship,
            DependencyType::Inheritance => $this->inheritance,
            DependencyType::FrameworkBinding => $this->frameworkBinding,
            DependencyType::Usage => $this->usage,
            DependencyType::Import => $this->import,
        };
    }

    /**
     * @param  array<mixed>  $weights
     */
    private static function weight(array $weights, DependencyType $type, int $default): int
    {
        $value = $weights[$type->value] ?? $default;

        if (! is_int($value) || $value < 0) {
            throw InvalidConfigurationException::invalidValue("weights.{$type->value}", 'a non-negative integer');
        }

        return $value;
    }
}
