<?php

namespace App\Console\Commands;

use App\Services\Retention\RetentionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ExportRetentionTombstonesCommand extends Command
{
    protected $signature = 'retention:tombstones-export {--after= : Export records after this ISO-8601 timestamp}';

    protected $description = 'Export a bounded PII-free tombstone journal for protected restore storage';

    public function handle(RetentionPolicy $policy): int
    {
        $after = $this->option('after');
        $query = DB::table('retention_tombstones')
            ->select(['id', 'batch_id', 'scope', 'record_id', 'action', 'key_id', 'subject_hmac', 'occurred_at'])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit($policy->batchSize());

        if ($after !== null && $after !== '') {
            $query->where('occurred_at', '>', CarbonImmutable::parse($after));
        }

        foreach ($query->get() as $record) {
            if (! is_string($record->occurred_at) && ! $record->occurred_at instanceof \DateTimeInterface) {
                throw new InvalidArgumentException('Stored tombstone timestamp is invalid.');
            }

            $this->line(json_encode([
                'id' => $record->id,
                'batch_id' => $record->batch_id,
                'scope' => $record->scope,
                'record_id' => $record->record_id,
                'action' => $record->action,
                'key_id' => $record->key_id,
                'subject_hmac' => $record->subject_hmac,
                'occurred_at' => CarbonImmutable::parse($record->occurred_at)->toIso8601String(),
            ], JSON_THROW_ON_ERROR));
        }

        return self::SUCCESS;
    }
}
