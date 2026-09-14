<?php

namespace App\Services\Retention;

use Illuminate\Support\Facades\DB;

final class RetentionExceptionService
{
    public function flag(string $scope, int $recordId, string $reasonCode): void
    {
        $now = now();
        DB::table('retention_exceptions')->upsert([[
            'scope' => $scope,
            'record_id' => $recordId,
            'reason_code' => $reasonCode,
            'first_detected_at' => $now,
            'last_detected_at' => $now,
            'resolved_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['scope', 'record_id', 'reason_code'], ['last_detected_at', 'resolved_at', 'updated_at']);
    }
}
