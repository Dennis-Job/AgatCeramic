<?php

namespace App\Services;

use App\Support\RussianPhoneNumber;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final class PhoneNumberNormalizationService
{
    /**
     * Only explicit, unambiguous values are rewritten; historical timestamps are preserved.
     *
     * @param  Closure(string, int, string): void  $reportIssue
     * @return array{checked: int, eligible: int, changed: int, unchanged: int, empty: int, invalid: int, multiple: int}
     */
    public function run(bool $apply, Closure $reportIssue): array
    {
        $counts = ['checked' => 0, 'eligible' => 0, 'changed' => 0, 'unchanged' => 0, 'empty' => 0, 'invalid' => 0, 'multiple' => 0];

        foreach (['contact_requests' => 'phone', 'orders' => 'customer_phone', 'stores' => 'phone', 'site_settings' => 'phones'] as $table => $column) {
            DB::table($table)->select('id')->chunkById(200, function (Collection $rows) use ($table, $column, $apply, $reportIssue, &$counts): void {
                /** @var Collection<int, object{id: int|string}> $rows */
                foreach ($rows as $row) {
                    $result = DB::transaction(fn (): string => $this->normalizeRow($table, $column, (int) $row->id, $apply));
                    $counts['checked']++;
                    if ($result === 'eligible') {
                        $counts['eligible']++;
                        $counts['changed'] += (int) $apply;
                    } else {
                        $counts[$result]++;
                    }

                    if ($result === 'invalid' || $result === 'multiple') {
                        $reportIssue($table, (int) $row->id, $result);
                    }
                }
            });
        }

        return $counts;
    }

    /** @return 'eligible'|'unchanged'|'empty'|'invalid'|'multiple' */
    private function normalizeRow(string $table, string $column, int $id, bool $apply): string
    {
        $row = DB::table($table)->where('id', $id)->lockForUpdate()->first([$column]);
        if (! $row instanceof stdClass) {
            return 'empty';
        }

        $original = $row->{$column};
        if ($original === null) {
            return 'empty';
        }

        $value = $original;
        if ($table === 'site_settings') {
            $phones = is_string($value) ? json_decode($value) : null;
            if (! is_array($phones) || ! array_is_list($phones)) {
                return 'invalid';
            }
            if ($phones === []) {
                return 'empty';
            }
            if (count($phones) > 1) {
                return 'multiple';
            }
            $value = $phones[0];
        }

        $normalized = RussianPhoneNumber::normalize($value);
        if ($normalized === null) {
            return 'invalid';
        }
        if ($normalized === $value) {
            return 'unchanged';
        }

        if ($apply) {
            DB::table($table)->where('id', $id)->update([
                $column => $table === 'site_settings' ? json_encode([$normalized], JSON_THROW_ON_ERROR) : $normalized,
            ]);
        }

        return 'eligible';
    }
}
