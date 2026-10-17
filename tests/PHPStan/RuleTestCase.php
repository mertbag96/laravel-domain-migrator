<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Tests\PHPStan;

use MertBag\DomainMigrator\PHPStan\DomainBoundaryRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase as PHPStanRuleTestCase;

/**
 * @extends PHPStanRuleTestCase<DomainBoundaryRule>
 */
abstract class RuleTestCase extends PHPStanRuleTestCase
{
    public string $mapPath = '';

    public ?string $baselinePath = null;

    protected function getRule(): Rule
    {
        return new DomainBoundaryRule($this->mapPath, $this->baselinePath);
    }
}
