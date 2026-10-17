<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Analysis\Graph\DependencyGraph;
use MertBag\DomainMigrator\Analysis\Graph\GraphBuilder;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\DependencyWeights;
use MertBag\DomainMigrator\Data\ScannedClass;
use MertBag\DomainMigrator\Data\SourceFile;
use MertBag\DomainMigrator\Enums\DeclarationType;
use MertBag\DomainMigrator\Enums\DependencyType;
use MertBag\DomainMigrator\Tests\PHPStan\RuleTestCase;
use MertBag\DomainMigrator\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(RuleTestCase::class)->in('PHPStan');

function fixturePath(string $path = ''): string
{
    return __DIR__.'/Fixtures/shop'.($path === '' ? '' : '/'.$path);
}

function fixtureFile(string $relativePath): SourceFile
{
    return new SourceFile(fixturePath($relativePath), $relativePath);
}

function fixtureOptions(): AnalysisOptions
{
    return new AnalysisOptions(
        basePath: fixturePath(),
        paths: ['app'],
        exclude: ['app/Providers/*'],
    );
}

/**
 * Builds a graph from class names. Classes in a Models namespace extend Eloquent; any other class extends
 * the parent given in $parents.
 *
 * @param  array<string, list<string>>  $dependencies  Class => classes it depends on.
 * @param  array<string, string>  $parents  Class => internal parent class.
 */
function graphOf(array $dependencies, array $parents = []): DependencyGraph
{
    $names = array_unique([...array_keys($dependencies), ...array_merge([], ...array_values($dependencies)), ...array_keys($parents), ...array_values($parents)]);

    return (new GraphBuilder)->build(array_values(array_map(function (string $name) use ($dependencies, $parents): ScannedClass {
        $parent = $parents[$name] ?? (str_contains($name, '\\Models\\') ? 'Illuminate\Database\Eloquent\Model' : null);
        $types = array_fill_keys($dependencies[$name] ?? [], [DependencyType::Relationship]);

        if (isset($parents[$name])) {
            $types[$parents[$name]] = [DependencyType::Inheritance];
        }

        return new ScannedClass(
            name: $name,
            declaration: DeclarationType::Class_,
            file: $name.'.php',
            parent: $parent,
            dependencies: $types,
        );
    }, $names)), new DependencyWeights);
}

function temporaryPhpFile(string $code): SourceFile
{
    $path = sys_get_temp_dir().'/domain-migrator-'.bin2hex(random_bytes(8)).'.php';
    file_put_contents($path, $code);

    register_shutdown_function(static fn (): bool => @unlink($path));

    return new SourceFile($path, basename($path));
}
