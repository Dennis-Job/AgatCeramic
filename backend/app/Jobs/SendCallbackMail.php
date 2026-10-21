<?php

namespace App\Jobs;

use App\Services\CallbackMailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCallbackMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $deliveryId) {}

    public function handle(CallbackMailService $mail): void
    {
        $mail->send($this->deliveryId);
    }
}
