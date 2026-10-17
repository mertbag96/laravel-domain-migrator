<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

/**
 * A domain as defined in the domain map.
 */
final readonly class DomainDefinition
{
    /**
     * @param  list<string>  $classes  Classes assigned to the domain, wherever they live.
     * @param  list<string>  $namespaces  Namespaces whose classes belong to the domain, e.g. App\Domain\Billing.
     * @param  ?list<string>  $dependsOn  Domains this domain may use. Null means the domain is not checked.
     */
    public function __construct(
        public string $name,
        public array $classes = [],
        public array $namespaces = [],
        public ?array $dependsOn = null,
    ) {}

    public function isChecked(): bool
    {
        return $this->dependsOn !== null;
    }

    public function mayUse(string $domain): bool
    {
        return $this->dependsOn === null || in_array($domain, $this->dependsOn, true);
    }
}
