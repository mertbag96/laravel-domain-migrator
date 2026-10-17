<?php

declare(strict_types=1);
use Illuminate\Console\Command;

arch('php preset')->preset()->php();

arch('security preset')->preset()->security();

arch('source files use strict types')
    ->expect('MertBag\DomainMigrator')
    ->toUseStrictTypes();

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('data objects are immutable')
    ->expect('MertBag\DomainMigrator\Data')
    ->toBeFinal()
    ->toBeReadonly();

arch('exceptions are final')
    ->expect('MertBag\DomainMigrator\Exceptions')
    ->toBeFinal()
    ->toExtend(Throwable::class);

arch('commands delegate to actions')
    ->expect('MertBag\DomainMigrator\Commands')
    ->toExtend(Command::class)
    ->not->toUse('PhpParser');

arch('the analysis core does not depend on the console layer')
    ->expect('MertBag\DomainMigrator\Analysis')
    ->not->toUse(['MertBag\DomainMigrator\Commands', 'MertBag\DomainMigrator\Reporting', 'MertBag\DomainMigrator\Boundaries', 'Illuminate\Console']);

arch('only the PHPStan integration depends on PHPStan, which is optional')
    ->expect('MertBag\DomainMigrator')
    ->not->toUse('PHPStan')
    ->ignoring('MertBag\DomainMigrator\PHPStan');

arch('boundary checks do not depend on the console layer or on clustering')
    ->expect('MertBag\DomainMigrator\Boundaries')
    ->not->toUse(['MertBag\DomainMigrator\Commands', 'MertBag\DomainMigrator\Reporting', 'MertBag\DomainMigrator\Analysis\Clustering', 'Illuminate\Console']);
