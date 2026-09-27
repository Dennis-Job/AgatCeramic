<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy placeholders have no defined file mapping. Never silently reinterpret their IDs.
        if (DB::table('categories')->whereNotNull('image_id')->exists()
            || DB::table('brands')->whereNotNull('logo_id')->exists()) {
            throw new RuntimeException('Reconcile non-null category image_id and brand logo_id values before installing Media Library.');
        }

        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 16);
            $table->string('disk', 32);
            $table->string('path', 512)->unique();
            $table->string('thumbnail_path', 512)->nullable()->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('title', 255);
            $table->string('alt', 255)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->foreign('image_id')->references('id')->on('media')->restrictOnDelete();
        });
        Schema::table('brands', function (Blueprint $table): void {
            $table->foreign('logo_id')->references('id')->on('media')->restrictOnDelete();
        });
        Schema::table('banners', function (Blueprint $table): void {
            $table->foreignId('image_media_id')->nullable()->constrained('media')->restrictOnDelete();
        });

        foreach (['brand_media_documents' => 'brand_id', 'category_media_documents' => 'category_id'] as $name => $owner) {
            Schema::create($name, function (Blueprint $table) use ($owner): void {
                $table->id();
                $table->foreignId($owner)->constrained()->cascadeOnDelete();
                $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique([$owner, 'media_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_media_documents');
        Schema::dropIfExists('brand_media_documents');
        Schema::table('banners', fn (Blueprint $table) => $table->dropConstrainedForeignId('image_media_id'));
        Schema::table('brands', fn (Blueprint $table) => $table->dropForeign(['logo_id']));
        Schema::table('categories', fn (Blueprint $table) => $table->dropForeign(['image_id']));
        Schema::dropIfExists('media');
    }
};
