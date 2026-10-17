<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrderAction;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, PlaceOrderAction $placeOrder): OrderResource
    {
        return new OrderResource($placeOrder->execute($request->user(), $request->validated('items')));
    }
}
