<?php

declare(strict_types=1);

use MertBag\DomainMigrator\Migration\Rewriting\PhpRewriter;

function rewriteCode(string $code, array $moves, array $known = [], ?string $newNamespace = null): string
{
    return (new PhpRewriter)->rewrite($code, 'app/File.php', $moves, array_fill_keys($known, true), $newNamespace)->code;
}

it('rewrites imports and fully qualified names', function (): void {
    $code = <<<'PHP'
    <?php

    namespace App\Http;

    use App\Models\Order;
    use App\Models\Order as Purchase;

    final class Thing
    {
        public function run(): string
        {
            return \App\Models\Order::class.Order::class.Purchase::class;
        }
    }
    PHP;

    $result = rewriteCode($code, ['App\Models\Order' => 'App\Domain\Orders\Models\Order']);

    expect($result)->toContain('use App\Domain\Orders\Models\Order;')
        ->and($result)->toContain('use App\Domain\Orders\Models\Order as Purchase;')
        ->and($result)->toContain('\App\Domain\Orders\Models\Order::class.Order::class.Purchase::class')
        ->and($result)->not->toContain('App\Models');
});

it('imports a moved class that was reached through the file namespace', function (): void {
    $code = <<<'PHP'
    <?php

    namespace App\Models;

    final class User
    {
        public function orders(): string
        {
            return Order::class;
        }
    }
    PHP;

    $result = rewriteCode($code, ['App\Models\Order' => 'App\Domain\Orders\Models\Order'], ['App\Models\Order', 'App\Models\User']);

    expect($result)->toContain("namespace App\Models;\n\nuse App\Domain\Orders\Models\Order;\n\nfinal class User");
});

it('imports what a moved file used to reach through its old namespace', function (): void {
    $code = <<<'PHP'
    <?php

    namespace App\Http\Controllers;

    use App\Domain\Orders\Models\Order;

    final class OrderController extends Controller
    {
        public function show(Order $order, \Exception $e): void {}
    }
    PHP;

    $result = rewriteCode($code, [], ['App\Http\Controllers\Controller'], 'App\Domain\Orders\Http\Controllers');

    expect($result)->toContain('namespace App\Domain\Orders\Http\Controllers;')
        ->and($result)->toContain("use App\Domain\Orders\Models\Order;\nuse App\Http\Controllers\Controller;");
});

it('keeps references to the file own namespace working when both move together', function (): void {
    $code = <<<'PHP'
    <?php

    namespace App\Models;

    final class Order
    {
        public function item(): string
        {
            return OrderItem::class.self::class;
        }
    }
    PHP;

    $moves = ['App\Models\Order' => 'App\Domain\Orders\Order', 'App\Models\OrderItem' => 'App\Domain\Orders\OrderItem'];
    $result = rewriteCode($code, $moves, ['App\Models\Order', 'App\Models\OrderItem'], 'App\Domain\Orders');

    expect($result)->toContain('namespace App\Domain\Orders;')
        ->and($result)->not->toContain('use ');
});

it('rewrites class names in strings with single and escaped backslashes', function (): void {
    $code = <<<'PHP'
    <?php

    return [
        'single' => 'App\Models\Order',
        'double' => "App\\Models\\Order",
        'other' => 'App\Models\OrderItem',
    ];
    PHP;

    $result = rewriteCode($code, ['App\Models\Order' => 'App\Domain\Orders\Models\Order']);

    expect($result)->toContain("'App\Domain\Orders\Models\Order'")
        ->and($result)->toContain('"App\\\\Domain\\\\Orders\\\\Models\\\\Order"')
        ->and($result)->toContain("'App\Models\OrderItem'");
});

it('reports a grouped import it cannot rewrite', function (): void {
    $code = "<?php\n\nnamespace App;\n\nuse App\\Models\\{Order, User};\n";

    $result = (new PhpRewriter)->rewrite($code, 'app/File.php', ['App\Models\Order' => 'App\Domain\Orders\Models\Order'], []);

    expect($result->code)->toBe($code)
        ->and($result->manual)->toHaveCount(1);
});
