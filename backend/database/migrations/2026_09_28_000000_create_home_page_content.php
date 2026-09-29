<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            $table->string('eyebrow')->nullable();
            $table->string('image_alt')->nullable();
        });

        Schema::create('home_pages', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('hero_slider_id')->nullable()->constrained('sliders')->nullOnDelete();
            $table->json('content');
            $table->timestamps();
        });

        $now = now();
        $existingSlider = DB::table('sliders')->where('slug', 'home-hero')->first(['id', 'is_published']);
        $hasPublishedSlides = $existingSlider !== null && DB::table('slider_banner')
            ->join('banners', 'banners.id', '=', 'slider_banner.banner_id')
            ->where('slider_banner.slider_id', $existingSlider->id)
            ->where('banners.is_published', true)
            ->exists();
        if ($existingSlider === null) {
            $sliderId = null;
        } else {
            $sliderId = $existingSlider->is_published && $hasPublishedSlides ? $existingSlider->id : null;
        }
        if ($sliderId === null) {
            $slug = 'home-hero';
            if ($existingSlider !== null) {
                $slug = 'home-hero-default';
                $suffix = 2;
                while (DB::table('sliders')->where('slug', $slug)->exists()) {
                    $slug = 'home-hero-default-'.$suffix++;
                }
            }
            $sliderId = DB::table('sliders')->insertGetId([
                'name' => 'Главная страница — главный экран',
                'slug' => $slug,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $slides = [
                ['AgatCeramic · Керамогранит', 'Пространство начинается с фактуры.', 'Керамогранит, плитка и мозаика для интерьеров, в которых важна каждая деталь.', '/images/home/hero-porcelain.webp', 'Светлый керамогранит с фактурой камня в современном интерьере'],
                ['AgatCeramic · Плитка', 'Тепло материала. Тишина формы.', 'От гладкой поверхности до выразительной ручной фактуры — найдите своё настроение.', '/images/home/category-tile.webp', 'Светлая керамическая плитка в интерьере ванной комнаты'],
                ['AgatCeramic · Мозаика', 'Акцент в каждой детали.', 'Мелкий формат помогает выделить нишу, стену или другую важную часть пространства.', '/images/home/category-mosaic.webp', 'Мозаичная поверхность в спокойных природных оттенках'],
            ];
            foreach ($slides as $position => [$eyebrow, $title, $description, $imageUrl, $imageAlt]) {
                $bannerId = DB::table('banners')->insertGetId([
                    'eyebrow' => $eyebrow, 'title' => $title, 'description' => $description,
                    'image_url' => $imageUrl, 'image_alt' => $imageAlt,
                    'link_label' => 'Смотреть направления', 'link_url' => '/#catalog',
                    'is_published' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('slider_banner')->insert(['slider_id' => $sliderId, 'banner_id' => $bannerId, 'position' => $position + 1]);
            }
        }

        DB::table('home_pages')->insert([
            'id' => 1,
            'hero_slider_id' => $sliderId,
            'content' => json_encode(config('home_page.content'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Seeded banners and slider remain: administrators may have edited or reused them.
        Schema::dropIfExists('home_pages');
        Schema::table('banners', function (Blueprint $table): void {
            $table->dropColumn(['eyebrow', 'image_alt']);
        });
    }
};
