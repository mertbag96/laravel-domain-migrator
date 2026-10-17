<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateInvoice implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function handle(InvoiceNumberGenerator $numbers): void
    {
        Invoice::create([
            'order_id' => $this->order->id,
            'number' => $numbers->next(),
        ]);
    }
}
