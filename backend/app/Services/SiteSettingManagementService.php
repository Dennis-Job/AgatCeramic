<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SiteSettingManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, array $attributes): SiteSetting
    {
        return DB::transaction(function () use ($actor, $attributes): SiteSetting {
            $settings = SiteSetting::query()->lockForUpdate()->findOrFail(1);
            $settings->fill($attributes);

            if ($settings->publish_bank_details && (! $settings->bank_name || ! $settings->bank_bik || ! $settings->bank_account || ! $settings->bank_correspondent_account)) {
                throw ValidationException::withMessages(['publish_bank_details' => 'Заполните все банковские реквизиты перед публикацией.']);
            }

            $changedKeys = array_keys($settings->getDirty());
            $settings->save();
            $this->auditLogService->record($actor, 'site-settings.updated', $settings, ['fields' => $changedKeys]);

            return $settings;
        });
    }
}
