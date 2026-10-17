<?php

namespace App\Actions;

use App\Events\OrderPlaced;
use App\Exceptions\OutOfStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

class PlaceOrderAction
{
    public function execute(User $user, array $items): Order
    {
        foreach ($items as $item) {
            if (Product::query()->whereKey($item['product_id'])->doesntExist()) {
                throw new OutOfStockException;
            }
        }

        $order = Order::create(['user_id' => $user->id]);

        OrderPlaced::dispatch($order);

        return $order;
    }
}
