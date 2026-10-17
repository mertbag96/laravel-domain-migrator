<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Analysis\Scanning\ClassScanner;
use MertBag\DomainMigrator\Enums\DeclarationType;
use MertBag\DomainMigrator\Enums\DependencyType;
use MertBag\DomainMigrator\Exceptions\UnparsableFileException;

it('collects relationships, attribute bindings and usages of a model', function (): void {
    [$order] = (new ClassScanner)->scan(fixtureFile('app/Models/Order.php'));

    expect($order->name)->toBe('App\Models\Order')
        ->and($order->declaration)->toBe(DeclarationType::Class_)
        ->and($order->file)->toBe('app/Models/Order.php')
        ->and($order->parent)->toBe('Illuminate\Database\Eloquent\Model')
        ->and($order->dependencies['App\Models\User'])->toContain(DependencyType::Relationship)
        ->and($order->dependencies['App\Models\OrderItem'])->toContain(DependencyType::Relationship)
        ->and($order->dependencies['App\Models\Invoice'])->toContain(DependencyType::Relationship)
        ->and($order->dependencies['App\Observers\OrderObserver'])->toContain(DependencyType::FrameworkBinding, DependencyType::Import)
        ->and($order->dependencies['App\Enums\OrderStatus'])->toContain(DependencyType::Usage, DependencyType::Import);
});

it('records the first parameter type of handler methods', function (): void {
    [$listener] = (new ClassScanner)->scan(fixtureFile('app/Listeners/QueueInvoiceGeneration.php'));

    expect($listener->handlerParameters)->toBe(['App\Events\OrderPlaced']);
});

it('records traits and interfaces as inheritance', function (): void {
    [$job] = (new ClassScanner)->scan(fixtureFile('app/Jobs/GenerateInvoice.php'));

    expect($job->interfaces)->toBe(['Illuminate\Contracts\Queue\ShouldQueue'])
        ->and($job->dependencies['Illuminate\Contracts\Queue\ShouldQueue'])->toContain(DependencyType::Inheritance)
        ->and($job->dependencies['Illuminate\Foundation\Queue\Queueable'])->toContain(DependencyType::Inheritance);
});

it('resolves grouped and aliased imports and nested types', function (): void {
    $file = temporaryPhpFile(<<<'PHP'
        <?php

        namespace App\Billing;

        use App\Models\{Customer, Plan as BillingPlan};
        use function App\Support\helper;

        final class Charger
        {
            public function charge(Customer|BillingPlan $target, ?Card $card): Receipt&\Stringable
            {
                try {
                    return static::make(self::DEFAULT, $card instanceof Wallet);
                } catch (DeclinedException $exception) {
                    throw $exception;
                }
            }
        }
        PHP);

    [$charger] = (new ClassScanner)->scan($file);

    expect(array_keys($charger->dependencies))->toEqualCanonicalizing([
        'App\Billing\Card',
        'App\Billing\DeclinedException',
        'App\Billing\Receipt',
        'App\Billing\Wallet',
        'App\Models\Customer',
        'App\Models\Plan',
        'Stringable',
    ]);
});

it('collects every class declared in a file and ignores anonymous classes', function (): void {
    $file = temporaryPhpFile(<<<'PHP'
        <?php

        namespace App;

        interface Shape {}

        trait Named {}

        enum Size: string { case Small = 's'; }

        class Square implements Shape
        {
            use Named;

            public function make(): Shape
            {
                return new class implements Shape {};
            }
        }
        PHP);

    $classes = (new ClassScanner)->scan($file);

    expect(array_map(fn ($class) => [$class->name, $class->declaration], $classes))->toBe([
        ['App\Shape', DeclarationType::Interface],
        ['App\Named', DeclarationType::Trait],
        ['App\Size', DeclarationType::Enum],
        ['App\Square', DeclarationType::Class_],
    ]);
});

it('throws for files with syntax errors', function (): void {
    (new ClassScanner)->scan(fixtureFile('app/Support/Broken.php'));
})->throws(UnparsableFileException::class);
