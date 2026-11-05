<?php

namespace App\Jobs;

use App\Services\OrderConfirmationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $orderId) {}

    public function handle(OrderConfirmationService $mail): void
    {
        $mail->sendLegacyConfirmation($this->orderId);
    }
}
