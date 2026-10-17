<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class DomainMigratorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('domain-migrator')
            ->hasConfigFile();
    }
}
