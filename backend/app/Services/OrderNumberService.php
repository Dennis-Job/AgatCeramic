<?php

namespace App\Services;

class OrderNumberService
{
    public function generate(): string
    {
        return 'AC-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(5)));
    }
}
