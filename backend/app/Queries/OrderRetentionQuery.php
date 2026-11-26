<?php

namespace App\Queries;

use App\Models\Order;
use App\Models\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class OrderRetentionQuery
{
    /** @return list<string> */
    public function terminalCodes(): array
    {
        $codes = OrderStatus::query()->where('is_terminal', true)->pluck('code')->all();

        return array_values(array_filter($codes, is_string(...)));
    }

    /**
     * @param  list<string>  $terminalCodes
     * @return Collection<int, Order>
     */
    public function activeForReview(array $terminalCodes, CarbonImmutable $cutoff, int $limit): Collection
    {
        return Order::query()
            ->whereNull('anonymized_at')
            ->when($terminalCodes !== [], fn (Builder $query): Builder => $query->whereNotIn('status', $terminalCodes))
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id']);
    }

    /**
     * @param  list<string>  $terminalCodes
     * @return Collection<int, Order>
     */
    public function missingTerminalAnchor(array $terminalCodes, int $limit): Collection
    {
        return Order::query()
            ->whereNull('anonymized_at')
            ->whereIn('status', $terminalCodes)
            ->whereNull('completed_at')
            ->whereNull('paid_at')
            ->whereDoesntHave('statusHistory', fn (Builder $query): Builder => $query->whereIn('to_status', $terminalCodes))
            ->orderBy('id')
            ->limit($limit)
            ->get(['id']);
    }

    /**
     * @param  list<string>  $terminalCodes
     * @return Builder<Order>
     */
    public function eligibleForDirectPiiExpiry(array $terminalCodes, CarbonImmutable $cutoff): Builder
    {
        return Order::query()
            ->whereNull('anonymized_at')
            ->whereIn('status', $terminalCodes)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('completed_at')
                ->orWhere('completed_at', '<', $cutoff))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('paid_at')
                ->orWhere('paid_at', '<', $cutoff))
            ->whereDoesntHave('statusHistory', fn (Builder $query): Builder => $query
                ->whereIn('to_status', $terminalCodes)
                ->where('occurred_at', '>=', $cutoff))
            ->where(fn (Builder $query): Builder => $query
                ->whereNotNull('completed_at')
                ->orWhereNotNull('paid_at')
                ->orWhereHas('statusHistory', fn (Builder $history): Builder => $history->whereIn('to_status', $terminalCodes)));
    }

    /** @return Builder<Order> */
    public function eligibleForCommercialRowExpiry(CarbonImmutable $cutoff): Builder
    {
        return Order::query()
            ->whereNotNull('anonymized_at')
            ->whereNotNull('commercial_retention_until')
            ->where('commercial_retention_until', '<=', $cutoff);
    }
}
