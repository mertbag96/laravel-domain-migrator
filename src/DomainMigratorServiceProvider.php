<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use MertBag\DomainMigrator\Actions\PlanMigrationAction;
use MertBag\DomainMigrator\Commands\AnalyzeCommand;
use MertBag\DomainMigrator\Commands\CheckCommand;
use MertBag\DomainMigrator\Commands\PlanCommand;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\BoundaryOptions;
use MertBag\DomainMigrator\Data\MigrationOptions;
use MertBag\DomainMigrator\Migration\Risks\BroadcastNameDetector;
use MertBag\DomainMigrator\Migration\Risks\ConflictDetector;
use MertBag\DomainMigrator\Migration\Risks\DatabaseNotificationDetector;
use MertBag\DomainMigrator\Migration\Risks\DiscoveryDetector;
use MertBag\DomainMigrator\Migration\Risks\FactoryDetector;
use MertBag\DomainMigrator\Migration\Risks\MorphTypeDetector;
use MertBag\DomainMigrator\Migration\Risks\PolicyDetector;
use MertBag\DomainMigrator\Migration\Risks\QueuedPayloadDetector;
use MertBag\DomainMigrator\Migration\Risks\RiskDetector;
use MertBag\DomainMigrator\Migration\Risks\StringReferenceDetector;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class DomainMigratorServiceProvider extends PackageServiceProvider
{
    /** @var list<class-string<RiskDetector>> */
    private const array RISK_DETECTORS = [
        ConflictDetector::class,
        MorphTypeDetector::class,
        QueuedPayloadDetector::class,
        DatabaseNotificationDetector::class,
        BroadcastNameDetector::class,
        FactoryDetector::class,
        PolicyDetector::class,
        DiscoveryDetector::class,
        StringReferenceDetector::class,
    ];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('domain-migrator')
            ->hasConfigFile()
            ->hasCommands([AnalyzeCommand::class, CheckCommand::class, PlanCommand::class]);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(AnalysisOptions::class, static fn (Application $app): AnalysisOptions => AnalysisOptions::fromConfig(
            $app->make(Repository::class)->get('domain-migrator'),
            $app->basePath(),
        ));

        $this->app->bind(BoundaryOptions::class, static fn (Application $app): BoundaryOptions => BoundaryOptions::fromConfig(
            $app->make(Repository::class)->get('domain-migrator'),
            $app->basePath(),
        ));

        $this->app->bind(MigrationOptions::class, static fn (Application $app): MigrationOptions => MigrationOptions::fromConfig(
            $app->make(Repository::class)->get('domain-migrator'),
        ));

        $this->app->when(PlanMigrationAction::class)
            ->needs(RiskDetector::class)
            ->give(static fn (Application $app): array => array_map($app->make(...), self::RISK_DETECTORS));
    }
}
