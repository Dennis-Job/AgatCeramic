<?php

namespace App\Queries;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Support\RussianPhoneNumber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class OrderQuery
{
    /** @return array{new: int, working: int, shipped: int} */
    public function statusCounts(): array
    {
        /** @var Collection<string, int|numeric-string> $counts */
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->where(function ($query): void {
                $query->whereIn('status', ['new', 'shipped'])
                    ->orWhereIn('status', OrderStatus::query()->select('code')
                        ->where('is_active', true)
                        ->where('is_terminal', false)
                        ->whereNotIn('code', ['new', 'shipped']));
            })
            ->groupBy('status')->toBase()->pluck('total', 'status');

        $working = 0;
        foreach ($counts->except(['new', 'shipped']) as $count) {
            $working += (int) $count;
        }

        return [
            'new' => (int) ($counts['new'] ?? 0),
            'working' => $working,
            'shipped' => (int) ($counts['shipped'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Order::query()->with(['items', 'assignee:id,name']);

        $search = $filters['search'] ?? null;
        $search = is_string($search) ? trim($search) : '';
        if ($search !== '') {
            $phoneSearch = RussianPhoneNumber::normalize($search);
            $query->where(function ($query) use ($search, $phoneSearch): void {
                $query->where('order_number', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%')
                    ->orWhere('customer_phone', 'like', '%'.$search.'%')
                    ->orWhere('customer_email', 'like', '%'.$search.'%');
                if ($phoneSearch !== null) {
                    $query->orWhere('customer_phone', $phoneSearch);
                }
            });
        }

        if (isset($filters['statuses']) && is_array($filters['statuses'])) {
            $query->whereIn('status', $filters['statuses']);
        }

        foreach (['status', 'payment_status'] as $filter) {
            $value = $filters[$filter] ?? null;
            $value = is_string($value) ? trim($value) : '';
            if ($value !== '') {
                $query->where($filter, $value);
            }
        }

        $assigneeId = $filters['assignee_id'] ?? null;
        if (is_int($assigneeId) || (is_string($assigneeId) && ctype_digit($assigneeId))) {
            $query->where('assignee_id', (int) $assigneeId);
        }

        if (filter_var($filters['unassigned'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->whereNull('assignee_id');
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    public function prepare(Order $order): Order
    {
        return $order->load(['items', 'assignee:id,name']);
    }
}
