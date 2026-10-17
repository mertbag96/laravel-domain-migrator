<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use MertBag\DomainMigrator\Commands\AnalyzeCommand;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class DomainMigratorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('domain-migrator')
            ->hasConfigFile()
            ->hasCommand(AnalyzeCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(AnalysisOptions::class, static fn (Application $app): AnalysisOptions => AnalysisOptions::fromConfig(
            $app->make(Repository::class)->get('domain-migrator'),
            $app->basePath(),
        ));
    }
}
