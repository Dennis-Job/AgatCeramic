<?php

namespace App\Queries;

use App\Models\ContactRequest;
use App\Support\RussianPhoneNumber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ContactRequestQuery
{
    /** @return array{new: int, processing: int, awaiting_client: int} */
    public function statusCounts(): array
    {
        /** @var Collection<string, int|numeric-string> $counts */
        $counts = ContactRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->whereIn('status', ['new', 'processing', 'awaiting_client'])
            ->groupBy('status')->toBase()->pluck('total', 'status');

        return [
            'new' => (int) ($counts['new'] ?? 0),
            'processing' => (int) ($counts['processing'] ?? 0),
            'awaiting_client' => (int) ($counts['awaiting_client'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ContactRequest>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ContactRequest::query()->with('assignee:id,name');

        $search = $filters['search'] ?? null;
        $search = is_string($search) ? trim($search) : '';
        if ($search !== '') {
            $phoneSearch = RussianPhoneNumber::normalize($search);
            $query->where(function ($query) use ($search, $phoneSearch): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('message', 'like', '%'.$search.'%');
                if ($phoneSearch !== null) {
                    $query->orWhere('phone', $phoneSearch);
                }
            });
        }

        foreach (['type', 'status'] as $filter) {
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

        if ($this->booleanFilter($filters['unassigned'] ?? false)) {
            $query->whereNull('assignee_id');
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    public function prepare(ContactRequest $contactRequest): ContactRequest
    {
        return $contactRequest->load('assignee:id,name');
    }

    private function booleanFilter(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
