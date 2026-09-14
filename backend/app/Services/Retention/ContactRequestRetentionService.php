<?php

namespace App\Services\Retention;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ContactRequestRetentionService
{
    public function __construct(
        private readonly RetentionPolicy $policy,
        private readonly RetentionEvidenceService $evidence,
        private readonly RetentionExceptionService $exceptions,
        private readonly ContactRequestDataDestructionService $destruction,
    ) {}

    /** @return list<RetentionBatchResult> */
    public function run(bool $dryRun): array
    {
        if (! $dryRun) {
            $this->policy->assertApplyAllowed('contacts');
        }

        return [
            $this->reviewActiveRequests($dryRun),
            $this->deleteTerminalRequests(ContactRequestStatus::Completed, $this->policy->contactCompletedCutoff(), $dryRun),
            $this->deleteTerminalRequests(ContactRequestStatus::Rejected, $this->policy->contactRejectedCutoff(), $dryRun),
        ];
    }

    private function reviewActiveRequests(bool $dryRun): RetentionBatchResult
    {
        $batchId = (string) Str::uuid();
        $reviewCutoff = $this->policy->contactReviewCutoff();
        $maximumCutoff = $this->policy->contactMaximumCutoff();
        $terminalStatuses = [ContactRequestStatus::Completed->value, ContactRequestStatus::Rejected->value];
        $requests = ContactRequest::query()
            ->whereNotIn('status', $terminalStatuses)
            ->where('updated_at', '<', $reviewCutoff)
            ->orderBy('id')
            ->limit($this->policy->batchSize())
            ->get(['id', 'updated_at']);

        if (! $dryRun) {
            foreach ($requests as $request) {
                $reason = $request->updated_at !== null && $request->updated_at->lessThan($maximumCutoff)
                    ? 'active_contact_maximum_age_exceeded'
                    : 'active_contact_review_required';
                $this->exceptions->flag('contacts', (int) $request->id, $reason);
            }
        }

        $result = new RetentionBatchResult(
            $batchId,
            'contacts',
            'active-review',
            $dryRun,
            $reviewCutoff,
            count($requests),
            0,
            0,
            count($requests),
        );
        $this->evidence->recordResult($result);

        return $result;
    }

    private function deleteTerminalRequests(ContactRequestStatus $status, CarbonImmutable $cutoff, bool $dryRun): RetentionBatchResult
    {
        $batchId = (string) Str::uuid();
        $action = $status->value.'-deletion';
        $missing = ContactRequest::query()
            ->where('status', $status->value)
            ->whereNull('completed_at')
            ->orderBy('id')
            ->limit($this->policy->batchSize())
            ->get(['id']);

        if (! $dryRun) {
            foreach ($missing as $request) {
                $this->exceptions->flag('contacts', (int) $request->id, 'terminal_timestamp_missing');
            }
        }

        $baseQuery = ContactRequest::query()
            ->where('status', $status->value)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<', $cutoff);
        $held = $this->heldCount(clone $baseQuery);

        if ($dryRun) {
            $eligible = count($this->unheld(clone $baseQuery)->orderBy('id')->limit($this->policy->batchSize())->get(['id']));
            $result = new RetentionBatchResult($batchId, 'contacts', $action, true, $cutoff, $eligible + $held, 0, $held, count($missing));
            $this->evidence->recordResult($result);

            return $result;
        }

        return DB::transaction(function () use ($batchId, $action, $cutoff, $status, $held, $missing): RetentionBatchResult {
            $requests = $this->lockBatch($this->unheld(
                ContactRequest::query()
                    ->where('status', $status->value)
                    ->whereNotNull('completed_at')
                    ->where('completed_at', '<', $cutoff)
            ));
            $processed = 0;

            foreach ($requests as $request) {
                if ($this->hasActiveHold((int) $request->id)) {
                    continue;
                }

                $subjectHmac = $this->evidence->subjectHmac('contacts', (int) $request->id, [
                    $request->name,
                    $request->phone,
                    $request->email,
                ]);
                $this->evidence->createTombstone($batchId, 'contacts', (int) $request->id, 'contact-deleted', $subjectHmac);
                $this->destruction->delete($request);
                $processed++;
            }

            $result = new RetentionBatchResult($batchId, 'contacts', $action, false, $cutoff, count($requests) + $held, $processed, $held, count($missing));
            $this->evidence->recordResult($result);

            return $result;
        });
    }

    /**
     * @param  Builder<ContactRequest>  $query
     * @return Builder<ContactRequest>
     */
    private function unheld(Builder $query): Builder
    {
        return $query->whereNotExists(fn (QueryBuilder $hold): QueryBuilder => $hold
            ->selectRaw('1')
            ->from('retention_legal_holds')
            ->where('scope', 'contacts')
            ->whereColumn('record_id', 'contact_requests.id')
            ->where('started_at', '<=', now())
            ->whereNull('released_at'));
    }

    /** @param Builder<ContactRequest> $query */
    private function heldCount(Builder $query): int
    {
        return count($query->whereExists(fn (QueryBuilder $hold): QueryBuilder => $hold
            ->selectRaw('1')
            ->from('retention_legal_holds')
            ->where('scope', 'contacts')
            ->whereColumn('record_id', 'contact_requests.id')
            ->where('started_at', '<=', now())
            ->whereNull('released_at'))
            ->orderBy('id')
            ->limit($this->policy->batchSize())
            ->get(['id']));
    }

    private function hasActiveHold(int $recordId): bool
    {
        return DB::table('retention_legal_holds')
            ->where('scope', 'contacts')
            ->where('record_id', $recordId)
            ->where('started_at', '<=', now())
            ->whereNull('released_at')
            ->exists();
    }

    /**
     * @param  Builder<ContactRequest>  $query
     * @return Collection<int, ContactRequest>
     */
    private function lockBatch(Builder $query): Collection
    {
        $lock = DB::getDriverName() === 'pgsql' ? 'FOR UPDATE SKIP LOCKED' : true;

        return $query->orderBy('id')->limit($this->policy->batchSize())->lock($lock)->get();
    }
}
