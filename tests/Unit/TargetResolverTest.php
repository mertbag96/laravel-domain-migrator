<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Data\ClassNode;
use MertBag\DomainMigrator\Data\MigrationOptions;
use MertBag\DomainMigrator\Data\Psr4Root;
use MertBag\DomainMigrator\Enums\ClassKind;
use MertBag\DomainMigrator\Enums\DeclarationType;
use MertBag\DomainMigrator\Migration\TargetResolver;

function nodeOf(string $class, string $file): ClassNode
{
    return new ClassNode($class, ClassKind::Other, DeclarationType::Class_, $file);
}

beforeEach(function (): void {
    $this->resolver = new TargetResolver;
    $this->roots = [new Psr4Root('App\\', 'app')];
});

it('builds the domain namespace from the configured namespace', function (): void {
    expect($this->resolver->namespaceOf('order items', new MigrationOptions))->toBe('App\Domain\OrderItems')
        ->and($this->resolver->namespaceOf('Billing', new MigrationOptions(namespace: 'Domain')))->toBe('Domain\Billing');
});

it('keeps the path of a class below its PSR-4 root', function (): void {
    $move = $this->resolver->resolve(nodeOf('App\Http\Controllers\Api\OrderController', 'app/Http/Controllers/Api/OrderController.php'), 'Orders', 'App\Domain\Orders', $this->roots);

    expect($move?->target)->toBe('App\Domain\Orders\Http\Controllers\Api\OrderController')
        ->and($move?->targetFile)->toBe('app/Domain/Orders/Http/Controllers/Api/OrderController.php')
        ->and($move?->file)->toBe('app/Http/Controllers/Api/OrderController.php');
});

it('drops a namespace segment that repeats the domain name', function (): void {
    $move = $this->resolver->resolve(nodeOf('App\Models\Labels\Label', 'app/Models/Labels/Label.php'), 'Labels', 'App\Domain\Labels', $this->roots);
    $singular = $this->resolver->resolve(nodeOf('App\Http\Controllers\Label\LabelController', 'app/Http/Controllers/Label/LabelController.php'), 'Labels', 'App\Domain\Labels', $this->roots);

    expect($move?->target)->toBe('App\Domain\Labels\Models\Label')
        ->and($singular?->target)->toBe('App\Domain\Labels\Http\Controllers\LabelController');
});

it('places the target under its own PSR-4 root', function (): void {
    $roots = [new Psr4Root('Domain\\', 'src/Domain'), new Psr4Root('App\\', 'app')];

    $move = $this->resolver->resolve(nodeOf('App\Models\Invoice', 'app/Models/Invoice.php'), 'Billing', 'Domain\Billing', $roots);

    expect($move?->target)->toBe('Domain\Billing\Models\Invoice')
        ->and($move?->targetFile)->toBe('src/Domain/Billing/Models/Invoice.php');
});

it('cannot resolve classes or targets outside every PSR-4 root', function (): void {
    expect($this->resolver->resolve(nodeOf('Legacy\Order', 'lib/Order.php'), 'Orders', 'App\Domain\Orders', $this->roots))->toBeNull()
        ->and($this->resolver->resolve(nodeOf('App\Models\Order', 'app/Models/Order.php'), 'Orders', 'Domain\Orders', $this->roots))->toBeNull();
});

it('knows which classes are already in place and where PSR-4 expects a class', function (): void {
    expect($this->resolver->isInPlace('App\Domain\Orders\Models\Order', 'App\Domain\Orders'))->toBeTrue()
        ->and($this->resolver->isInPlace('App\Domain\OrdersArchive\Order', 'App\Domain\Orders'))->toBeFalse()
        ->and($this->resolver->expectedPath('App\Models\Order', $this->roots))->toBe('app/Models/Order.php');
});
