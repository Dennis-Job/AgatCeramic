<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_image_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('original_filename');
            $table->string('disk', 32);
            $table->string('path');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedInteger('total_folders')->default(0);
            $table->unsignedInteger('processed_folders')->default(0);
            $table->unsignedInteger('created_images')->default(0);
            $table->unsignedInteger('replaced_images')->default(0);
            $table->unsignedInteger('failed_folders')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_image_import_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_image_import_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 255)->nullable();
            $table->string('entry', 1024)->nullable();
            $table->json('messages');
            $table->timestamps();
            $table->index('product_image_import_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_import_errors');
        Schema::dropIfExists('product_image_imports');
    }
};
