<?php

namespace App\Services;

class InvoiceNumberGenerator
{
    public function next(): string
    {
        return 'INV-'.random_int(1000, 9999);
    }
}
