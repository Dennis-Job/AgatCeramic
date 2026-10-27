<?php

use App\Support\PageBlocks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('home_pages')->orderBy('id')->lockForUpdate()->each(function (object $home): void {
                /** @var object{id: int, content: string} $home */
                $content = $this->decode($home->content);
                $updated = $this->separateSection($content);
                if ($updated !== $content) {
                    DB::table('home_pages')->where('id', $home->id)->update(['content' => $this->json($updated)]);
                }
            });
            DB::table('pages')->orderBy('id')->lockForUpdate()->each(function (object $page): void {
                /** @var object{id: int, blocks: string|null, published_snapshot: string|null} $page */
                $blocks = $page->blocks === null ? [] : $this->decode($page->blocks);
                $updatedBlocks = $this->separateBlocks($blocks);
                $snapshot = $page->published_snapshot === null ? null : $this->decode($page->published_snapshot);
                $updatedSnapshot = $snapshot;
                if ($snapshot !== null && is_array($snapshot['blocks'] ?? null)) {
                    $updatedSnapshot['blocks'] = $this->separateBlocks($snapshot['blocks']);
                }
                $updates = [];
                if ($blocks !== $updatedBlocks) {
                    $updates['blocks'] = $this->json($updatedBlocks);
                }
                if ($snapshot !== $updatedSnapshot && $updatedSnapshot !== null) {
                    $updates['published_snapshot'] = $this->json($updatedSnapshot);
                }
                if ($updates !== []) {
                    // Do not change timestamps, publication state or unrelated draft data.
                    DB::table('pages')->where('id', $page->id)->update($updates);
                }
                foreach (PageBlocks::mediaIds([$updatedBlocks, $updatedSnapshot ?? []]) as $mediaId) {
                    if (DB::table('media')->where('id', $mediaId)->exists()) {
                        DB::table('page_media')->insertOrIgnore(['page_id' => $page->id, 'media_id' => $mediaId]);
                    }
                }
            });
        });
    }

    /** @param array<mixed> $content
     * @return array<mixed>
     */
    private function separateSection(array $content): array
    {
        if (is_array($content['materials'] ?? null) && ! array_key_exists('items', $content['materials'])) {
            $categories = $content['categories'] ?? [];
            $content['materials']['items'] = is_array($categories) ? ($categories['items'] ?? []) : [];
        }

        return $content;
    }

    /** @param array<mixed> $blocks
     * @return array<mixed>
     */
    private function separateBlocks(array $blocks): array
    {
        $items = [];
        foreach ($blocks as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'categories' && is_array($block['data'] ?? null)) {
                $items = $block['data']['items'] ?? [];
                break;
            }
        }
        foreach ($blocks as &$block) {
            if (is_array($block) && ($block['type'] ?? null) === 'materials' && is_array($block['data'] ?? null) && ! array_key_exists('items', $block['data'])) {
                $block['data']['items'] = $items;
            }
        }

        return $blocks;
    }

    /** @return array<mixed> */
    private function decode(string $value): array
    {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new LogicException('Stored page content must be an object or array.');
        }

        return $decoded;
    }

    /** @param array<mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public function down(): void
    {
        // Independent lists may have been edited after migration. Removing them would
        // lose content; application rollback can retain this additive JSON field.
        throw new RuntimeException('Banner menu items contain independent content. Restore a verified pre-migration backup to downgrade data; application rollback may retain the additive items field.');
    }
};
