<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sliders', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('slider_banner', function (Blueprint $table): void {
            $table->foreignId('slider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('banner_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->primary(['slider_id', 'banner_id']);
            $table->unique(['slider_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slider_banner');
        Schema::dropIfExists('sliders');
    }
};
