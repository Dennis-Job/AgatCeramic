<?php

namespace App\Queries;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class ContactRequestRetentionQuery
{
    /** @return Collection<int, ContactRequest> */
    public function activeForReview(CarbonImmutable $cutoff, int $limit): Collection
    {
        return ContactRequest::query()
            ->whereNotIn('status', [ContactRequestStatus::Completed->value, ContactRequestStatus::Rejected->value])
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'updated_at']);
    }

    /** @return Collection<int, ContactRequest> */
    public function missingTerminalTimestamp(ContactRequestStatus $status, int $limit): Collection
    {
        return ContactRequest::query()
            ->where('status', $status->value)
            ->whereNull('completed_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id']);
    }

    /** @return Builder<ContactRequest> */
    public function eligibleForDeletion(ContactRequestStatus $status, CarbonImmutable $cutoff): Builder
    {
        return ContactRequest::query()
            ->where('status', $status->value)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<', $cutoff);
    }
}
