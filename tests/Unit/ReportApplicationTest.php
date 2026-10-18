<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Data\ReportApplication;

it('reads the application name from config/app.php', function (): void {
    $root = sys_get_temp_dir().'/domain-migrator-app-'.uniqid('', true);
    mkdir($root.'/config', recursive: true);
    file_put_contents($root.'/config/app.php', <<<'PHP'
        <?php

        return ['name' => 'Snipe-IT'];
        PHP);

    $application = ReportApplication::fromProjectRoot($root);

    expect($application->name)->toBe('Snipe-IT')
        ->and($application->basePath)->toBe(realpath($root));

    array_map(unlink(...), glob($root.'/config/*'));
    rmdir($root.'/config');
    rmdir($root);
});
