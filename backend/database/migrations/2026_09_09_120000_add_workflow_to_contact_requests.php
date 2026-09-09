<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->string('status', 32)->default('new');
            $table->timestamp('completed_at')->nullable();
            $table->index(['status', 'created_at']);
        });

        Schema::create('contact_request_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_request_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('actor_snapshot')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['contact_request_id', 'occurred_at', 'id']);
        });

        Schema::create('contact_request_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('author_snapshot')->nullable();
            $table->text('body');
            $table->timestamp('created_at');
            $table->index(['contact_request_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_request_comments');
        Schema::dropIfExists('contact_request_status_histories');
        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['status', 'completed_at']);
        });
    }
};
