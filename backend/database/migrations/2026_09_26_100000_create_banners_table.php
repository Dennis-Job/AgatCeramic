<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->string('link_label')->nullable();
            $table->string('link_url', 2048)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['is_published', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
