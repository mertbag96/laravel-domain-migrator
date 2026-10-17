<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Jobs\GenerateInvoice;

class QueueInvoiceGeneration
{
    public function handle(OrderPlaced $event): void
    {
        GenerateInvoice::dispatch($event->order);
    }
}
