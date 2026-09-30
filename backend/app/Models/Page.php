<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property list<array{id: string, type: string, enabled: bool, data: array<string, mixed>}> $blocks
 * @property array<string, mixed> $seo
 * @property array<string, mixed>|null $published_snapshot
 * @property Carbon|null $published_at
 * @property string|null $published_slug
 * @property array<string, mixed>|null $site_layout
 */
#[Fillable(['title', 'slug', 'body', 'is_published', 'blocks', 'seo', 'published_snapshot', 'published_at', 'published_slug', 'site_layout'])]
class Page extends Model
{
    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'blocks' => 'array', 'seo' => 'array', 'published_snapshot' => 'array', 'published_at' => 'datetime', 'site_layout' => 'array'];
    }
}
