<?php

namespace App\Queries;

use App\Models\ContactRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ContactStatisticsQuery
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

    /** @return array{partner: int, callback: int, completed: int, rejected: int} */
    private function counts(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $counts = ['partner' => 0, 'callback' => 0, 'completed' => 0, 'rejected' => 0];
        // Group only by non-personal dimensions; do not load customer records or history.
        /** @var Collection<int, object{type: string, status: string, total: int|numeric-string}> $groups */
        $groups = ContactRequest::query()
            ->where('created_at', '>=', $start->utc())
            ->where('created_at', '<', $end->utc())
            ->selectRaw('type, status, COUNT(*) as total')
            ->groupBy('type', 'status')->toBase()->get();
        foreach ($groups as $group) {
            $total = (int) $group->total;
            if ($group->type === 'partner' || $group->type === 'callback') {
                $counts[$group->type] += $total;
            }
            if ($group->status === 'completed' || $group->status === 'rejected') {
                $counts[$group->status] += $total;
            }
        }

        return $counts;
    }
}
