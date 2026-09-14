<?php

namespace App\Services\Retention;

use App\Models\ContactRequest;
use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class TombstoneReplayService
{
    public function __construct(
        private readonly RetentionPolicy $policy,
        private readonly RetentionEvidenceService $evidence,
        private readonly OrderRetentionAnchorService $anchors,
        private readonly OrderDataDestructionService $orders,
        private readonly ContactRequestDataDestructionService $contacts,
    ) {}

    /** @param list<RetentionTombstoneRecord> $records */
    public function replay(array $records, bool $dryRun): RetentionBatchResult
    {
        if (! $dryRun) {
            $this->policy->assertApplyAllowed('restore');
            if (collect($records)->contains(fn (RetentionTombstoneRecord $record): bool => $record->scope === 'orders')) {
                $this->policy->assertApplyAllowed('orders');
            }
        }

        $records = array_slice($records, 0, $this->policy->batchSize());
        $batchId = (string) Str::uuid();
        $held = 0;
        $eligible = 0;

        foreach ($records as $record) {
            if ($this->hasActiveHold($record)) {
                $held++;
            } else {
                $eligible++;
            }
        }

        if ($dryRun) {
            $result = new RetentionBatchResult($batchId, 'restore', 'tombstone-replay', true, null, $eligible + $held, 0, $held, 0);
            $this->evidence->recordResult($result);

            return $result;
        }

        return DB::transaction(function () use ($records, $batchId, $held): RetentionBatchResult {
            $processed = 0;

            foreach ($records as $record) {
                if ($this->hasActiveHold($record)) {
                    continue;
                }

                $processed += $this->apply($record);
                $this->evidence->importTombstone($record);
            }

            $result = new RetentionBatchResult($batchId, 'restore', 'tombstone-replay', false, null, count($records), $processed, $held, 0);
            $this->evidence->recordResult($result);

            return $result;
        });
    }

    private function apply(RetentionTombstoneRecord $record): int
    {
        return match ($record->action) {
            'order-anonymized' => $this->anonymizeOrder($record),
            'order-deleted' => $this->deleteOrder($record, true),
            'commercial-row-deleted' => $this->deleteOrder($record, false),
            'contact-deleted' => $this->deleteContact($record),
            default => throw new RuntimeException('Unsupported tombstone action.'),
        };
    }

    private function anonymizeOrder(RetentionTombstoneRecord $record): int
    {
        $order = Order::query()->whereKey($record->recordId)->lockForUpdate()->first();
        if ($order === null || $order->anonymized_at !== null) {
            return 0;
        }

        $this->verifyOrderFingerprint($order, $record);
        $terminalCodes = array_values(OrderStatus::query()
            ->where('is_terminal', true)
            ->pluck('code')
            ->filter(static fn (mixed $code): bool => is_string($code))
            ->values()
            ->all());
        $anchor = $this->anchors->latest($order, $terminalCodes);
        if ($anchor === null) {
            throw new RuntimeException('Restored order retention anchor is missing.');
        }

        $this->orders->anonymize($order, $anchor);

        return 1;
    }

    private function deleteOrder(RetentionTombstoneRecord $record, bool $verifyFingerprint): int
    {
        $order = Order::query()->whereKey($record->recordId)->lockForUpdate()->first();
        if ($order === null) {
            return 0;
        }

        if ($verifyFingerprint) {
            $this->verifyOrderFingerprint($order, $record);
        } elseif ($order->anonymized_at === null) {
            throw new RuntimeException('Commercial deletion cannot target a non-anonymized restored order.');
        }

        $this->orders->delete($order);

        return 1;
    }

    private function deleteContact(RetentionTombstoneRecord $record): int
    {
        $request = ContactRequest::query()->whereKey($record->recordId)->lockForUpdate()->first();
        if ($request === null) {
            return 0;
        }

        $actual = $this->evidence->subjectHmac('contacts', (int) $request->id, [
            $request->name,
            $request->phone,
            $request->email,
        ], $record->keyId);
        if ($record->subjectHmac === null || ! hash_equals($record->subjectHmac, $actual)) {
            throw new RuntimeException('Restored contact fingerprint mismatch.');
        }

        $this->contacts->delete($request);

        return 1;
    }

    private function verifyOrderFingerprint(Order $order, RetentionTombstoneRecord $record): void
    {
        $actual = $this->evidence->subjectHmac('orders', (int) $order->id, [
            $order->customer_name,
            $order->customer_phone,
            $order->customer_email,
            $order->delivery_address,
        ], $record->keyId);
        if ($record->subjectHmac === null || ! hash_equals($record->subjectHmac, $actual)) {
            throw new RuntimeException('Restored order fingerprint mismatch.');
        }
    }

    private function hasActiveHold(RetentionTombstoneRecord $record): bool
    {
        return DB::table('retention_legal_holds')
            ->where('scope', $record->scope)
            ->where('record_id', $record->recordId)
            ->where('started_at', '<=', now())
            ->whereNull('released_at')
            ->exists();
    }
}
