<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $pages = [
            'delivery' => [
                'title' => 'Доставка',
                'heading' => 'Согласуем детали доставки',
                'body' => 'Уточните возможность, стоимость и сроки доставки по вашему адресу у менеджера.',
            ],
            'payment' => [
                'title' => 'Оплата',
                'heading' => 'Условия оплаты заказа',
                'body' => 'Онлайн-оплата на сайте не предусмотрена. Условия получения и оплаты согласовываются при оформлении заказа.',
            ],
            'designers' => [
                'title' => 'Дизайнерам',
                'heading' => 'Подбор материалов для вашего проекта',
                'body' => 'Вопросы по материалам и сотрудничеству можно обсудить с менеджером.',
            ],
        ];

        DB::transaction(function () use ($pages): void {
            foreach ($pages as $slug => $content) {
                // Either address belongs to an existing editor/published page.
                // Keep its content and publication state completely untouched.
                if (DB::table('pages')->where('slug', $slug)->orWhere('published_slug', $slug)->exists()) {
                    continue;
                }
                $blocks = [['id' => 'body', 'type' => 'text', 'enabled' => true, 'data' => ['title' => $content['heading'], 'body' => $content['body']]]];
                $seo = ['title' => $content['title'].' — AgatCeramic', 'description' => $content['body'], 'og_title' => $content['title'].' — AgatCeramic', 'og_description' => $content['body'], 'og_image_url' => null, 'og_image_media_id' => null];
                // Published and editable, but owner can enable indexing after
                // adding the full business terms to this initial concise copy.
                $snapshot = ['title' => $content['title'], 'slug' => $slug, 'body' => $content['body'], 'blocks' => $blocks, 'seo' => $seo, 'is_indexable' => false, 'intro' => null];
                DB::table('pages')->insert([
                    'title' => $content['title'], 'slug' => $slug, 'body' => $content['body'],
                    'blocks' => json_encode($blocks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'seo' => json_encode($seo, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'intro' => null, 'is_published' => true, 'is_indexable' => false,
                    'published_slug' => $slug,
                    'published_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Content may have been edited since installation. Rollback must not
        // remove owner-managed pages or their published snapshots.
    }
};
