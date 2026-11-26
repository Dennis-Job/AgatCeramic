<?php

namespace App\Services\Retention;

use App\Enums\ContactRequestStatus;
use App\Queries\ActiveLegalHoldQuery;
use App\Queries\ContactRequestRetentionQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ContactRequestRetentionService
{
    public function __construct(
        private readonly RetentionPolicy $policy,
        private readonly RetentionEvidenceService $evidence,
        private readonly RetentionExceptionService $exceptions,
        private readonly ContactRequestDataDestructionService $destruction,
        private readonly ContactRequestRetentionQuery $selection,
        private readonly ActiveLegalHoldQuery $legalHolds,
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
        $requests = $this->selection->activeForReview($reviewCutoff, $this->policy->batchSize());

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
        $limit = $this->policy->batchSize();
        $missing = $this->selection->missingTerminalTimestamp($status, $limit);

        if (! $dryRun) {
            foreach ($missing as $request) {
                $this->exceptions->flag('contacts', (int) $request->id, 'terminal_timestamp_missing');
            }
        }

        $candidates = $this->selection->eligibleForDeletion($status, $cutoff);
        $held = $this->legalHolds->heldCount($candidates, 'contacts', $limit);

        if ($dryRun) {
            $eligible = count($this->legalHolds->unheldBatch($candidates, 'contacts', $limit)->get(['id']));
            $result = new RetentionBatchResult($batchId, 'contacts', $action, true, $cutoff, $eligible + $held, 0, $held, count($missing));
            $this->evidence->recordResult($result);

            return $result;
        }

        return DB::transaction(function () use ($batchId, $action, $cutoff, $candidates, $limit, $held, $missing): RetentionBatchResult {
            $lock = DB::getDriverName() === 'pgsql' ? 'FOR UPDATE SKIP LOCKED' : true;
            $requests = $this->legalHolds->unheldBatch($candidates, 'contacts', $limit)->lock($lock)->get();
            $processed = 0;

            foreach ($requests as $request) {
                if ($this->legalHolds->exists('contacts', (int) $request->id)) {
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
}
