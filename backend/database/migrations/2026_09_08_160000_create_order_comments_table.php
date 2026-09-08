<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('author_snapshot')->nullable();
            $table->text('body');
            $table->timestamp('created_at');
            $table->index(['order_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_comments');
    }
};
