<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Support\Facades\DB;

final class GuestCartCleanupService
{
    public function prune(int $limit): int
    {
        if ($limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('Guest cart cleanup limit must be between 1 and 1000.');
        }

        return DB::transaction(function () use ($limit): int {
            $cutoff = now();
            $query = Cart::query()
                ->where('expires_at', '<=', $cutoff)
                ->orderBy('expires_at')
                ->orderBy('id')
                ->limit($limit);
            if (DB::getDriverName() === 'pgsql') {
                $query->lock('FOR UPDATE SKIP LOCKED');
            } else {
                $query->lockForUpdate();
            }
            $ids = $query->pluck('id');

            if ($ids->isEmpty()) {
                return 0;
            }

            $deleted = Cart::query()
                ->whereKey($ids)
                ->where('expires_at', '<=', $cutoff)
                ->delete();

            return is_int($deleted) ? $deleted : 0;
        });
    }
}
