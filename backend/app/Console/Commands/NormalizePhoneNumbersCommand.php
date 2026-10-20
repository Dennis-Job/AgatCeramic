<?php

namespace App\Console\Commands;

use App\Services\PhoneNumberNormalizationService;
use Illuminate\Console\Command;

final class NormalizePhoneNumbersCommand extends Command
{
    protected $signature = 'phones:normalize {--apply : Apply unambiguous conversions after backup and review}';

    protected $description = 'Inspect legacy phone numbers; dry-run by default, never prints phone values';

    public function handle(PhoneNumberNormalizationService $service): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'mode=apply' : 'mode=dry-run');
        $counts = $service->run($apply, function (string $table, int $id, string $issue): void {
            $this->warn("table={$table} id={$id} issue={$issue}");
        });
        $this->info(implode(' ', array_map(
            static fn (string $key, int $value): string => "{$key}={$value}",
            array_keys($counts),
            array_values($counts),
        )));

        return $counts['invalid'] + $counts['multiple'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
