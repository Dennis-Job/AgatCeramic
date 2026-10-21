<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('callback_mail_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_request_id')->constrained()->cascadeOnDelete();
            $table->string('recipient', 16);
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['contact_request_id', 'recipient']);
            $table->index(['status', 'last_dispatched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('callback_mail_deliveries');
    }
};
