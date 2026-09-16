<?php

namespace App\Queries;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminUserQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = User::query()->with('roles');

        $search = $filters['search'] ?? null;
        $search = is_string($search) ? trim($search) : '';
        if ($search !== '') {
            $pattern = '%'.mb_strtolower($search).'%';
            $query->where(static function ($query) use ($pattern): void {
                $query->whereRaw('LOWER(name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$pattern]);
            });
        }

        $status = $filters['status'] ?? null;
        $status = is_string($status) ? trim($status) : '';
        if ($status !== '') {
            $query->where('status', $status);
        }

        return $query->orderBy('name')->paginate($perPage)->withQueryString();
    }

    public function prepare(User $user): User
    {
        return $user->load('roles');
    }
}
