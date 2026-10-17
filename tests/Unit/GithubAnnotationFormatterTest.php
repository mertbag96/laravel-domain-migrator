<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Data\BaselineEntry;
use MertBag\DomainMigrator\Data\BoundaryCheckResult;
use MertBag\DomainMigrator\Data\Violation;
use MertBag\DomainMigrator\Enums\DependencyType;
use MertBag\DomainMigrator\Reporting\GithubAnnotationFormatter;

it('formats violations as escaped workflow commands', function (): void {
    $result = new BoundaryCheckResult(
        violations: [new Violation('App\Jobs\Charge', 'App\Models\Order', 'Billing', 'Orders', 'app/Jobs/Charge, Old.php', null, [DependencyType::Usage])],
        baselined: [],
        fixedEntries: [new BaselineEntry('App\A', 'App\B')],
        uncheckedDomains: [],
        unmappedClasses: [],
        skippedFiles: [],
        classCount: 2,
    );

    expect((new GithubAnnotationFormatter)->format($result, 'domain-map.json'))->toBe(implode(PHP_EOL, [
        '::error file=app/Jobs/Charge%2C Old.php,line=1,title=Domain boundary%3A Billing → Orders::App\Jobs\Charge uses App\Models\Order, but Billing does not list Orders in depends_on in domain-map.json.',
        '::notice::1 baseline entry no longer occurs. Run php artisan domain:check --generate-baseline to remove them.',
    ]));
});
