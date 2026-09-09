<?php

namespace App\Jobs;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $orderId) {}

    public function handle(): void
    {
        $order = Order::query()->with('items')->find($this->orderId);

        if ($order === null || $order->customer_email === null) {
            return;
        }

        Mail::to($order->customer_email)->send(new OrderConfirmationMail($order));
    }
}
