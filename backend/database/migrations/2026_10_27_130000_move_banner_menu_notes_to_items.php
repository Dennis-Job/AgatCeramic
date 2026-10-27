<?php

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
                $updated = $content;
                if (is_array($content['materials'] ?? null)) {
                    $updated['materials'] = $this->copyNote($content['materials']);
                }
                if ($updated !== $content) {
                    DB::table('home_pages')->where('id', $home->id)->update(['content' => $this->json($updated)]);
                }
            });
            DB::table('pages')->orderBy('id')->lockForUpdate()->each(function (object $page): void {
                /** @var object{id: int, blocks: string|null, published_snapshot: string|null} $page */
                $blocks = $page->blocks === null ? [] : $this->decode($page->blocks);
                $updatedBlocks = $this->copyBlockNotes($blocks);
                $snapshot = $page->published_snapshot === null ? null : $this->decode($page->published_snapshot);
                $updatedSnapshot = $snapshot;
                if ($snapshot !== null && is_array($snapshot['blocks'] ?? null)) {
                    $updatedSnapshot['blocks'] = $this->copyBlockNotes($snapshot['blocks']);
                }
                $updates = [];
                if ($blocks !== $updatedBlocks) {
                    $updates['blocks'] = $this->json($updatedBlocks);
                }
                if ($snapshot !== $updatedSnapshot && $updatedSnapshot !== null) {
                    $updates['published_snapshot'] = $this->json($updatedSnapshot);
                }
                if ($updates !== []) {
                    // Backfill each version in place, preserving timestamps and publication metadata.
                    DB::table('pages')->where('id', $page->id)->update($updates);
                }
            });
        });
    }

    /** @param array<mixed> $data
     * @return array<mixed>
     */
    private function copyNote(array $data): array
    {
        if (! array_key_exists('note', $data) || ! is_array($data['items'] ?? null)) {
            return $data;
        }
        foreach ($data['items'] as &$item) {
            if (is_array($item) && ! array_key_exists('note', $item)) {
                $item['note'] = $data['note'];
            }
        }

        return $data;
    }

    /** @param array<mixed> $blocks
     * @return array<mixed>
     */
    private function copyBlockNotes(array $blocks): array
    {
        foreach ($blocks as &$block) {
            if (is_array($block) && ($block['type'] ?? null) === 'materials' && is_array($block['data'] ?? null)) {
                $block['data'] = $this->copyNote($block['data']);
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
        throw new RuntimeException('Banner menu notes contain independent content. Retain the additive fields on application rollback or restore a verified pre-migration backup to downgrade data.');
    }
};
