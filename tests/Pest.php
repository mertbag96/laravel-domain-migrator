<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\SourceFile;
use MertBag\DomainMigrator\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

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

function temporaryPhpFile(string $code): SourceFile
{
    $path = tempnam(sys_get_temp_dir(), 'domain-migrator').'.php';
    file_put_contents($path, $code);

    return new SourceFile($path, basename($path));
}
