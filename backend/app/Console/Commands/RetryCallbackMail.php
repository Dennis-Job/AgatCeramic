<?php

namespace App\Console\Commands;

use App\Models\CallbackMailDelivery;
use App\Services\CallbackMailService;
use Illuminate\Console\Command;

class RetryCallbackMail extends Command
{
    protected $signature = 'callbacks:retry-mail {--retry-exhausted : Reset up to 100 exhausted pending deliveries after fixing SMTP}';

    protected $description = 'Redispatch up to 100 pending callback email deliveries (technical IDs only)';

    public function handle(CallbackMailService $mail): int
    {
        if ($this->option('retry-exhausted')) {
            foreach (CallbackMailDelivery::query()->where('status', 'pending')->where('attempts', '>=', 9)->orderBy('id')->limit(100)->get() as $delivery) {
                $mail->resetExhausted($delivery);
            }
        }
        $deliveries = CallbackMailDelivery::query()->where('status', 'pending')->where('attempts', '<', 9)
            ->where(fn ($query) => $query->whereNull('last_dispatched_at')->orWhere('last_dispatched_at', '<=', now()->subMinutes(5)))
            ->orderBy('id')->limit(100)->get();
        foreach ($deliveries as $delivery) {
            $mail->dispatch($delivery);
        }
        $this->info('Callback email deliveries dispatched: '.$deliveries->count());

        return self::SUCCESS;
    }
}
