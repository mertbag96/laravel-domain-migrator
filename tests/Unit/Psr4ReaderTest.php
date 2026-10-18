<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Data\Psr4Root;
use MertBag\DomainMigrator\Exceptions\InvalidMigrationPlanException;
use MertBag\DomainMigrator\Migration\Psr4Reader;

it('reads the PSR-4 roots from composer.json, longest namespace first', function (): void {
    expect((new Psr4Reader)->read(blogPath()))->toEqual([
        new Psr4Root('Database\\Factories\\', 'database/factories'),
        new Psr4Root('App\\', 'app'),
    ]);
});

it('falls back to App\ in app/ without composer.json', function (): void {
    expect((new Psr4Reader)->read(fixturePath()))->toEqual([new Psr4Root('App\\', 'app')]);
});

it('rejects a composer.json without a readable autoload section', function (): void {
    $directory = blogCopy(['composer.json' => '{"autoload": "app/"}']);

    (new Psr4Reader)->read($directory);
})->throws(InvalidMigrationPlanException::class, 'The autoload section of');
