<?php

namespace App\Console\Commands;

use App\Enums\AdminUserStatus;
use App\Models\User;
use App\Services\LegacyCollectionMigrationService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Validation\ValidationException;
use Throwable;

class MigrateProductCollections extends Command
{
    protected $signature = 'catalog:collections-migrate
        {--apply : Apply unambiguous assignments instead of read-only preflight}
        {--actor= : Active authorized admin user ID for the audit trail}
        {--backup-verified : Confirm backup and restore were verified}
        {--enable-category=* : Explicit category IDs to enable for collections}';

    protected $description = 'Inspect legacy product collections, then explicitly transfer safe brand-scoped values';

    public function handle(LegacyCollectionMigrationService $service, Application $application): int
    {
        if (! $this->option('apply')) {
            $this->line(json_encode($service->scan(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }
        if (! $this->option('backup-verified') || (! $application->environment('testing') && ! $application->isDownForMaintenance())) {
            $this->error('Для переноса нужны проверенный backup (--backup-verified) и режим обслуживания с остановленными записями каталога.');

            return self::FAILURE;
        }
        $actorId = filter_var($this->option('actor'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $actor = $actorId === false ? null : User::query()->find($actorId);
        if ($actor === null || $actor->getAttribute('status') !== AdminUserStatus::Active) {
            $this->error('Укажите действующего сотрудника с правом управления каталогом через --actor.');

            return self::FAILURE;
        }
        $categoryIds = [];
        foreach ($this->option('enable-category') as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                $this->error('Каждый --enable-category должен быть положительным ID.');

                return self::FAILURE;
            }
            $categoryIds[] = $id;
        }
        try {
            $report = $service->apply($actor, array_values(array_unique($categoryIds)));
        } catch (ValidationException $exception) {
            $this->error(implode(' ', $exception->validator->errors()->all()));

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Перенос отменён. Проверьте права сотрудника, доступность БД и повторите preflight.');

            return self::FAILURE;
        }
        $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $report['ready_for_cutover'] ? self::SUCCESS : self::FAILURE;
    }
}
