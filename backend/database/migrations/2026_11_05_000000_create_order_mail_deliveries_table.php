<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_mail_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('recipient', ['admin', 'customer']);
            $table->enum('status', ['pending', 'sent', 'skipped'])->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'recipient']);
            $table->index(['status', 'last_dispatched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_mail_deliveries');
    }
};
