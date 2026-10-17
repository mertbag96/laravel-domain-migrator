<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Tests;

use MertBag\DomainMigrator\DomainMigratorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            DomainMigratorServiceProvider::class,
        ];
    }
}
