<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Services\GuestCartCleanupService;
use Illuminate\Console\Command;

final class PruneGuestCartsCommand extends Command
{
    protected $signature = 'cart:prune {--limit= : Maximum carts to delete in one run}';

    protected $description = 'Delete one bounded batch of expired guest carts';

    public function handle(GuestCartCleanupService $service): int
    {
        $configuredLimit = $this->option('limit') ?? config('cart.cleanup_batch_size');
        $limit = filter_var($configuredLimit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('The cleanup limit must be an integer between 1 and 1000.');

            return self::FAILURE;
        }

        $deleted = $service->prune($limit);
        $remaining = Cart::query()->where('expires_at', '<=', now())->count();
        $this->info("Deleted {$deleted} expired guest cart(s); {$remaining} remain eligible.");

        return self::SUCCESS;
    }
}
