<?php

namespace App\Queries;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class OrderStatisticsQuery
{
    private const TIMEZONE = 'Europe/Moscow';

    /**
     * @return array{month: string, previous_month: string, timezone: string, metrics: array<string, array{count: int, previous_count: int, change_percent: float|null}>}
     */
    public function forMonth(string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, self::TIMEZONE);
        if ($start === null) {
            throw new \InvalidArgumentException('Invalid statistics month.');
        }
        $previousStart = $start->subMonth();
        $current = $this->counts($start, $start->addMonth());
        $previous = $this->counts($previousStart, $start);
        $metrics = [];
        foreach ($current as $metric => $count) {
            $previousCount = $previous[$metric];
            $metrics[$metric] = [
                'count' => $count,
                'previous_count' => $previousCount,
                'change_percent' => $previousCount === 0
                    ? ($count === 0 ? 0.0 : null)
                    : round(($count - $previousCount) / $previousCount * 100, 1),
            ];
        }

        return [
            'month' => $month,
            'previous_month' => $previousStart->format('Y-m'),
            'timezone' => self::TIMEZONE,
            'metrics' => $metrics,
        ];
    }

    /** @return array{total: int, new: int, completed: int, cancelled: int} */
    private function counts(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $counts = ['total' => 0, 'new' => 0, 'completed' => 0, 'cancelled' => 0];
        // Aggregate non-personal dimensions without loading customer records or history.
        /** @var Collection<int, object{status: string, total: int|numeric-string}> $groups */
        $groups = Order::query()
            ->where('created_at', '>=', $start->utc())
            ->where('created_at', '<', $end->utc())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->toBase()->get();
        foreach ($groups as $group) {
            $total = (int) $group->total;
            $counts['total'] += $total;
            if (in_array($group->status, ['new', 'completed', 'cancelled'], true)) {
                $counts[$group->status] += $total;
            }
        }

        return $counts;
    }
}
