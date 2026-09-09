<?php

namespace App\Services;

use App\Jobs\SendOrderConfirmation;
use App\Models\Order;

class OrderConfirmationService
{
    public function queue(Order $order): void
    {
        if ($order->customer_email === null) {
            return;
        }

        SendOrderConfirmation::dispatch($order->id)->afterCommit();
    }
}
