<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Analysis\Graph\KindResolver;
use MertBag\DomainMigrator\Data\ScannedClass;
use MertBag\DomainMigrator\Enums\ClassKind;
use MertBag\DomainMigrator\Enums\DeclarationType;

function scannedClass(string $name, ?string $parent = null, DeclarationType $declaration = DeclarationType::Class_): ScannedClass
{
    return new ScannedClass($name, $declaration, 'app/'.$name.'.php', $parent);
}

it('resolves kinds from framework base classes', function (string $parent, ClassKind $kind): void {
    $class = scannedClass('Acme\Thing', $parent);

    expect((new KindResolver([$class]))->resolve($class))->toBe($kind);
})->with([
    ['Illuminate\Database\Eloquent\Model', ClassKind::Model],
    ['Illuminate\Foundation\Auth\User', ClassKind::Model],
    ['Illuminate\Foundation\Http\FormRequest', ClassKind::FormRequest],
    ['Illuminate\Http\Resources\Json\JsonResource', ClassKind::Resource],
    ['Illuminate\Mail\Mailable', ClassKind::Mail],
    ['Illuminate\Database\Eloquent\Builder', ClassKind::Builder],
    ['RuntimeException', ClassKind::Exception],
]);

it('resolves kinds from framework interfaces', function (string $interface, ClassKind $kind): void {
    $class = new ScannedClass('Acme\Thing', DeclarationType::Class_, 'app/Acme/Thing.php', interfaces: [$interface]);

    expect((new KindResolver([$class]))->resolve($class))->toBe($kind);
})->with([
    ['Illuminate\Contracts\Validation\ValidationRule', ClassKind::Rule],
    ['Illuminate\Contracts\Database\Eloquent\CastsAttributes', ClassKind::Cast],
]);

it('only treats classes in a Models namespace as models when they extend Eloquent', function (): void {
    $model = scannedClass('App\Models\Order', 'Illuminate\Database\Eloquent\Model');
    $label = scannedClass('App\Models\Labels\Sheet', 'App\Models\Labels\Label');
    $base = scannedClass('App\Models\Labels\Label');

    $resolver = new KindResolver([$model, $label, $base]);

    expect($resolver->resolve($model))->toBe(ClassKind::Model)
        ->and($resolver->resolve($label))->toBe(ClassKind::Other)
        ->and($resolver->resolve($base))->toBe(ClassKind::Other);
});

it('inherits the kind of an internal parent class', function (): void {
    $base = scannedClass('Acme\Support\BaseModel', 'Illuminate\Database\Eloquent\Model');
    $child = scannedClass('Acme\Support\Thing', 'Acme\Support\BaseModel');

    expect((new KindResolver([$base, $child]))->resolve($child))->toBe(ClassKind::Model);
});

it('falls back to the namespace and then the class name', function (string $name, ClassKind $kind): void {
    $class = scannedClass($name);

    expect((new KindResolver([$class]))->resolve($class))->toBe($kind);
})->with([
    ['App\Http\Controllers\Controller', ClassKind::Controller],
    ['App\Listeners\SendReceipt', ClassKind::Listener],
    ['App\Http\Responses\LoginResponse', ClassKind::Response],
    ['App\Support\RedirectResponse', ClassKind::Response],
    ['App\Domain\Billing\Actions\ChargeCustomer', ClassKind::Action],
    ['App\Support\InvoiceService', ClassKind::Service],
    ['App\Repositories\SongRepository', ClassKind::Repository],
    ['App\Models\Builders\AssetBuilder', ClassKind::Builder],
    ['App\Rules\Uppercase', ClassKind::Rule],
    ['App\Casts\AsMoney', ClassKind::Cast],
    ['App\Support\Money', ClassKind::Other],
]);

it('uses the declaration type for interfaces, traits and enums', function (DeclarationType $declaration, ClassKind $kind): void {
    $class = scannedClass('App\Models\Thing', declaration: $declaration);

    expect((new KindResolver([$class]))->resolve($class))->toBe($kind);
})->with([
    [DeclarationType::Interface, ClassKind::Interface],
    [DeclarationType::Trait, ClassKind::Trait],
    [DeclarationType::Enum, ClassKind::Enum],
]);

it('does not loop on circular inheritance', function (): void {
    $a = scannedClass('Acme\A', 'Acme\B');
    $b = scannedClass('Acme\B', 'Acme\A');

    expect((new KindResolver([$a, $b]))->resolve($a))->toBe(ClassKind::Other);
});
