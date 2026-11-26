<?php

namespace App\Services\Retention;

use App\Models\Order;
use App\Queries\ActiveLegalHoldQuery;
use App\Queries\OrderRetentionQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
        private readonly OrderRetentionQuery $selection,
        private readonly ActiveLegalHoldQuery $legalHolds,
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
        $terminalCodes = $this->selection->terminalCodes();
        $orders = $this->selection->activeForReview($terminalCodes, $cutoff, $this->policy->batchSize());

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
        $terminalCodes = $this->selection->terminalCodes();

        if ($terminalCodes === []) {
            return $this->recordEmpty($batchId, 'direct-pii-expiry', $dryRun, $cutoff);
        }

        $missing = $this->flagMissingTerminalAnchor($terminalCodes, $dryRun);
        $candidates = $this->selection->eligibleForDirectPiiExpiry($terminalCodes, $cutoff);
        $held = $this->legalHolds->heldCount($candidates, 'orders', $this->policy->batchSize());

        if ($dryRun) {
            $eligible = $this->countUnheldCandidates($candidates);
            $result = new RetentionBatchResult($batchId, 'orders', 'direct-pii-expiry', true, $cutoff, $eligible + $held, 0, $held, $missing);
            $this->evidence->recordResult($result);

            return $result;
        }

        return DB::transaction(function () use ($batchId, $cutoff, $terminalCodes, $candidates, $held, $missing): RetentionBatchResult {
            $orders = $this->lockUnheldCandidates($candidates);
            $processed = 0;

            foreach ($orders as $order) {
                if ($this->legalHolds->exists('orders', (int) $order->id)) {
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
        $candidates = $this->selection->eligibleForCommercialRowExpiry($cutoff);
        $held = $this->legalHolds->heldCount($candidates, 'orders', $this->policy->batchSize());

        if ($dryRun) {
            $eligible = $this->countUnheldCandidates($candidates);
            $result = new RetentionBatchResult($batchId, 'orders', 'commercial-row-expiry', true, $cutoff, $eligible + $held, 0, $held, 0);
            $this->evidence->recordResult($result);

            return $result;
        }

        return DB::transaction(function () use ($batchId, $cutoff, $candidates, $held): RetentionBatchResult {
            $orders = $this->lockUnheldCandidates($candidates);
            $processed = 0;

            foreach ($orders as $order) {
                if ($this->legalHolds->exists('orders', (int) $order->id)) {
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

    /** @param list<string> $terminalCodes */
    private function flagMissingTerminalAnchor(array $terminalCodes, bool $dryRun): int
    {
        $orders = $this->selection->missingTerminalAnchor($terminalCodes, $this->policy->batchSize());

        if (! $dryRun) {
            foreach ($orders as $order) {
                $this->exceptions->flag('orders', (int) $order->id, 'terminal_timestamp_missing');
            }
        }

        return count($orders);
    }

    /** @param Builder<Order> $candidates */
    private function countUnheldCandidates(Builder $candidates): int
    {
        return count($this->legalHolds->unheldBatch($candidates, 'orders', $this->policy->batchSize())->get(['id']));
    }

    /**
     * @param  Builder<Order>  $candidates
     * @return Collection<int, Order>
     */
    private function lockUnheldCandidates(Builder $candidates): Collection
    {
        $lock = DB::getDriverName() === 'pgsql' ? 'FOR UPDATE SKIP LOCKED' : true;

        return $this->legalHolds->unheldBatch($candidates, 'orders', $this->policy->batchSize())
            ->lock($lock)
            ->get();
    }

    private function recordEmpty(string $batchId, string $action, bool $dryRun, CarbonImmutable $cutoff): RetentionBatchResult
    {
        $result = new RetentionBatchResult($batchId, 'orders', $action, $dryRun, $cutoff, 0, 0, 0, 0);
        $this->evidence->recordResult($result);

        return $result;
    }
}
