<?php

namespace App\Console\Commands;

use App\Models\CheckoutIdempotencyKey;
use Illuminate\Console\Command;

class PruneCheckoutIdempotencyKeysCommand extends Command
{
    protected $signature = 'checkout-idempotency:prune';

    protected $description = 'Delete expired checkout idempotency keys and request hashes';

    public function handle(): int
    {
        $deleted = CheckoutIdempotencyKey::query()->where('expires_at', '<=', now())->delete();
        $this->info('Deleted '.(is_int($deleted) ? $deleted : 0).' expired checkout idempotency key(s).');

        return self::SUCCESS;
    }
}
