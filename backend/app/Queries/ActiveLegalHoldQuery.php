<?php

namespace App\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class ActiveLegalHoldQuery
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $candidates
     * @return Builder<TModel>
     */
    public function unheldBatch(Builder $candidates, string $scope, int $limit): Builder
    {
        $id = $candidates->qualifyColumn('id');

        return (clone $candidates)
            ->whereNotExists($this->forCandidate($scope, $id))
            ->orderBy($id)
            ->limit($limit);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $candidates
     */
    public function heldCount(Builder $candidates, string $scope, int $limit): int
    {
        $id = $candidates->qualifyColumn('id');

        return count((clone $candidates)
            ->whereExists($this->forCandidate($scope, $id))
            ->orderBy($id)
            ->limit($limit)
            ->get([$id]));
    }

    public function exists(string $scope, int $recordId): bool
    {
        return $this->activeForScope($scope)->where('record_id', $recordId)->exists();
    }

    private function forCandidate(string $scope, string $candidateIdColumn): QueryBuilder
    {
        return $this->activeForScope($scope)->selectRaw('1')->whereColumn('record_id', $candidateIdColumn);
    }

    private function activeForScope(string $scope): QueryBuilder
    {
        return DB::table('retention_legal_holds')
            ->where('scope', $scope)
            ->where('started_at', '<=', now())
            ->whereNull('released_at');
    }
}
