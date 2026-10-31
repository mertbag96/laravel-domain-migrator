<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Data\BoundaryOptions;

it('resolves relative boundary paths against the base path', function (): void {
    $options = BoundaryOptions::fromConfig(['boundaries' => ['map' => 'maps/domains.json']], '/srv/app/');

    expect($options->mapPath)->toBe('/srv/app/maps/domains.json')
        ->and($options->baselinePath)->toBe('/srv/app/domain-baseline.json');
});

it('keeps absolute boundary paths, including Windows drive paths', function (string $path, string $expected): void {
    $options = BoundaryOptions::fromConfig(['boundaries' => ['map' => $path]], '/srv/app');

    expect($options->mapPath)->toBe($expected);
})->with([
    'unix' => ['/var/maps/domains.json', '/var/maps/domains.json'],
    'windows backslashes' => ['C:\Users\me\domains.json', 'C:/Users/me/domains.json'],
    'windows slashes' => ['D:/maps/domains.json', 'D:/maps/domains.json'],
]);
