<?php

namespace App\Console\Commands;

use App\Models\OrderMailDelivery;
use App\Services\OrderConfirmationService;
use Illuminate\Console\Command;

class RetryOrderMail extends Command
{
    protected $signature = 'orders:retry-mail {--retry-exhausted : Reset up to 100 exhausted deliveries after fixing SMTP}';

    protected $description = 'Redispatch up to 100 pending order emails (technical IDs only)';

    public function handle(OrderConfirmationService $mail): int
    {
        if ($this->option('retry-exhausted')) {
            foreach (OrderMailDelivery::query()->where('status', 'pending')->where('attempts', '>=', 9)->orderBy('id')->limit(100)->get() as $delivery) {
                $mail->resetExhausted($delivery);
            }
        }
        $deliveries = OrderMailDelivery::query()->where('status', 'pending')->where('attempts', '<', 9)
            ->where(fn ($query) => $query->whereNull('last_dispatched_at')->orWhere('last_dispatched_at', '<=', now()->subMinutes(5)))
            ->orderByRaw('last_dispatched_at IS NOT NULL')->orderBy('last_dispatched_at')->orderBy('id')->limit(100)->get();
        foreach ($deliveries as $delivery) {
            $mail->dispatch($delivery);
        }
        $this->info('Order email deliveries dispatched: '.$deliveries->count());

        return self::SUCCESS;
    }
}
