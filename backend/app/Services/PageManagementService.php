<?php

namespace App\Services;

use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PageManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Page
    {
        return DB::transaction(function () use ($actor, $attributes): Page {
            $page = Page::query()->create($attributes);
            $this->auditLogService->record($actor, 'page.created', $page);

            return $page->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Page $page, array $attributes): Page
    {
        return DB::transaction(function () use ($actor, $page, $attributes): Page {
            $page->fill($attributes)->save();
            $this->auditLogService->record($actor, 'page.updated', $page);

            return $page;
        });
    }

    public function delete(User $actor, Page $page): void
    {
        DB::transaction(function () use ($actor, $page): void {
            $this->auditLogService->record($actor, 'page.deleted', $page);
            $page->delete();
        });
    }
}
