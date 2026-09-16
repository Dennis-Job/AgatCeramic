<?php

namespace App\Queries;

use App\Models\ContactRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ContactRequestQuery
{
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
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('message', 'like', '%'.$search.'%');
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
