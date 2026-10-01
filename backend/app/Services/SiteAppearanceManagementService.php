<?php

namespace App\Services;

use App\Models\HomePage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SiteAppearanceManagementService
{
    public function __construct(
        private readonly HomePageManagementService $home,
        private readonly PageManagementService $pages,
        private readonly AuditLogService $audit,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, array $attributes): void
    {
        $this->home->update($actor, $attributes, 'site-appearance.updated');
    }

    public function publish(User $actor): void
    {
        DB::transaction(function () use ($actor): void {
            HomePage::query()->lockForUpdate()->findOrFail(1);
            $page = Page::query()->where('slug', 'home')->lockForUpdate()->firstOrFail();
            $snapshot = $page->published_snapshot;
            if ($snapshot === null || $page->site_layout === null) {
                throw new \LogicException('The system home row must retain its initialized appearance snapshot.');
            }
            $snapshot['site_layout'] = $page->site_layout;
            $page->published_snapshot = $snapshot;
            $this->pages->syncMedia($page);
            $page->save();
            $this->audit->record($actor, 'site-appearance.published', $page);
        });
    }
}
