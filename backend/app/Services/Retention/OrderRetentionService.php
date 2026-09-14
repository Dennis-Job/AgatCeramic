<?php

namespace App\Services\Retention;

use App\Models\Order;
use App\Models\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OrderRetentionService
{
    public function __construct(
        private readonly RetentionPolicy $policy,
        private readonly RetentionEvidenceService $evidence,
        private readonly RetentionExceptionService $exceptions,
        private readonly OrderDataDestructionService $destruction,
        private readonly OrderRetentionAnchorService $anchors,
    ) {}

    /** @return list<RetentionBatchResult> */
    public function run(bool $dryRun): array
    {
        if (! $dryRun) {
            $this->policy->assertApplyAllowed('orders');
        }

        $results = [$this->reviewActiveOrders($dryRun), $this->expireDirectPii($dryRun)];

        if ($this->policy->commercialDisposition() === 'retain_commercial') {
            $results[] = $this->deleteExpiredCommercialRows($dryRun);
        }

        return $results;
    }

    private function reviewActiveOrders(bool $dryRun): RetentionBatchResult
    {
        $batchId = (string) Str::uuid();
        $cutoff = $this->policy->orderReviewCutoff();
        $terminalCodes = $this->terminalCodes();
        $orders = Order::query()
            ->whereNull('anonymized_at')
            ->when($terminalCodes !== [], fn (Builder $query): Builder => $query->whereNotIn('status', $terminalCodes))
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->limit($this->policy->batchSize())
            ->get(['id']);

        if (! $dryRun) {
            foreach ($orders as $order) {
                $this->exceptions->flag('orders', (int) $order->id, 'active_order_review_required');
            }
        }

        $result = new RetentionBatchResult(
            $batchId,
            'orders',
            'active-review',
            $dryRun,
            $cutoff,
            count($orders),
            0,
            0,
            count($orders),
        );
        $this->evidence->recordResult($result);

        return $result;
    }

    private function expireDirectPii(bool $dryRun): RetentionBatchResult
    {
        $batchId = (string) Str::uuid();
        $cutoff = $this->policy->orderPiiCutoff();
        $terminalCodes = $this->terminalCodes();

        if ($terminalCodes === []) {
            return $this->recordEmpty($batchId, 'direct-pii-expiry', $dryRun, $cutoff);
        }

        $missing = $this->flagMissingTerminalAnchor($terminalCodes, $dryRun);
        $baseQuery = $this->eligibleForDirectPiiExpiry($terminalCodes, $cutoff);
        $held = $this->heldCount(clone $baseQuery);

        if ($dryRun) {
            $eligible = count($this->unheld(clone $baseQuery)->orderBy('id')->limit($this->policy->batchSize())->get(['id']));
            $result = new RetentionBatchResult($batchId, 'orders', 'direct-pii-expiry', true, $cutoff, $eligible + $held, 0, $held, $missing);
            $this->evidence->recordResult($result);

            return $result;
        }

        return DB::transaction(function () use ($batchId, $cutoff, $terminalCodes, $held, $missing): RetentionBatchResult {
            $orders = $this->lockBatch($this->unheld($this->eligibleForDirectPiiExpiry($terminalCodes, $cutoff)));
            $processed = 0;

            foreach ($orders as $order) {
                if ($this->hasActiveHold((int) $order->id)) {
                    continue;
                }

                $anchor = $this->anchors->latest($order, $terminalCodes);
                if ($anchor === null || ! $anchor->lessThan($cutoff)) {
                    continue;
                }

                $subjectHmac = $this->evidence->subjectHmac('orders', (int) $order->id, [
                    $order->customer_name,
                    $order->customer_phone,
                    $order->customer_email,
                    $order->delivery_address,
                ]);
                $action = $this->policy->commercialDisposition() === 'delete_all'
                    ? 'order-deleted'
                    : 'order-anonymized';
                $this->evidence->createTombstone($batchId, 'orders', (int) $order->id, $action, $subjectHmac);

                if ($action === 'order-deleted') {
                    $this->destruction->delete($order);
                } else {
                    $this->destruction->anonymize($order, $anchor);
                }

                $processed++;
            }

            $result = new RetentionBatchResult($batchId, 'orders', 'direct-pii-expiry', false, $cutoff, count($orders) + $held, $processed, $held, $missing);
            $this->evidence->recordResult($result);

            return $result;
        });
    }

    private function deleteExpiredCommercialRows(bool $dryRun): RetentionBatchResult
    {
        $batchId = (string) Str::uuid();
        $cutoff = CarbonImmutable::now();
        $baseQuery = Order::query()
            ->whereNotNull('anonymized_at')
            ->whereNotNull('commercial_retention_until')
            ->where('commercial_retention_until', '<=', $cutoff);
        $held = $this->heldCount(clone $baseQuery);

        if ($dryRun) {
            $eligible = count($this->unheld(clone $baseQuery)->orderBy('id')->limit($this->policy->batchSize())->get(['id']));
            $result = new RetentionBatchResult($batchId, 'orders', 'commercial-row-expiry', true, $cutoff, $eligible + $held, 0, $held, 0);
            $this->evidence->recordResult($result);

            return $result;
        }

        return DB::transaction(function () use ($batchId, $cutoff, $held): RetentionBatchResult {
            $orders = $this->lockBatch($this->unheld(
                Order::query()
                    ->whereNotNull('anonymized_at')
                    ->whereNotNull('commercial_retention_until')
                    ->where('commercial_retention_until', '<=', $cutoff)
            ));
            $processed = 0;

            foreach ($orders as $order) {
                if ($this->hasActiveHold((int) $order->id)) {
                    continue;
                }

                $this->evidence->createTombstone($batchId, 'orders', (int) $order->id, 'commercial-row-deleted', null);
                $this->destruction->delete($order);
                $processed++;
            }

            $result = new RetentionBatchResult($batchId, 'orders', 'commercial-row-expiry', false, $cutoff, count($orders) + $held, $processed, $held, 0);
            $this->evidence->recordResult($result);

            return $result;
        });
    }

    /**
     * @param  list<string>  $terminalCodes
     * @return Builder<Order>
     */
    private function eligibleForDirectPiiExpiry(array $terminalCodes, CarbonImmutable $cutoff): Builder
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

    /** @param list<string> $terminalCodes */
    private function flagMissingTerminalAnchor(array $terminalCodes, bool $dryRun): int
    {
        $orders = Order::query()
            ->whereNull('anonymized_at')
            ->whereIn('status', $terminalCodes)
            ->whereNull('completed_at')
            ->whereNull('paid_at')
            ->whereDoesntHave('statusHistory', fn (Builder $query): Builder => $query->whereIn('to_status', $terminalCodes))
            ->orderBy('id')
            ->limit($this->policy->batchSize())
            ->get(['id']);

        if (! $dryRun) {
            foreach ($orders as $order) {
                $this->exceptions->flag('orders', (int) $order->id, 'terminal_timestamp_missing');
            }
        }

        return count($orders);
    }

    /** @return list<string> */
    private function terminalCodes(): array
    {
        $codes = OrderStatus::query()->where('is_terminal', true)->pluck('code')->all();

        return array_values(array_filter($codes, is_string(...)));
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    private function unheld(Builder $query): Builder
    {
        return $query->whereNotExists(fn (QueryBuilder $hold): QueryBuilder => $hold
            ->selectRaw('1')
            ->from('retention_legal_holds')
            ->where('scope', 'orders')
            ->whereColumn('record_id', 'orders.id')
            ->where('started_at', '<=', now())
            ->whereNull('released_at'));
    }

    /** @param Builder<Order> $query */
    private function heldCount(Builder $query): int
    {
        return count($query->whereExists(fn (QueryBuilder $hold): QueryBuilder => $hold
            ->selectRaw('1')
            ->from('retention_legal_holds')
            ->where('scope', 'orders')
            ->whereColumn('record_id', 'orders.id')
            ->where('started_at', '<=', now())
            ->whereNull('released_at'))
            ->orderBy('id')
            ->limit($this->policy->batchSize())
            ->get(['id']));
    }

    private function hasActiveHold(int $recordId): bool
    {
        return DB::table('retention_legal_holds')
            ->where('scope', 'orders')
            ->where('record_id', $recordId)
            ->where('started_at', '<=', now())
            ->whereNull('released_at')
            ->exists();
    }

    /**
     * @param  Builder<Order>  $query
     * @return Collection<int, Order>
     */
    private function lockBatch(Builder $query): Collection
    {
        $lock = DB::getDriverName() === 'pgsql' ? 'FOR UPDATE SKIP LOCKED' : true;

        return $query->orderBy('id')->limit($this->policy->batchSize())->lock($lock)->get();
    }

    private function recordEmpty(string $batchId, string $action, bool $dryRun, CarbonImmutable $cutoff): RetentionBatchResult
    {
        $result = new RetentionBatchResult($batchId, 'orders', $action, $dryRun, $cutoff, 0, 0, 0, 0);
        $this->evidence->recordResult($result);

        return $result;
    }
}
