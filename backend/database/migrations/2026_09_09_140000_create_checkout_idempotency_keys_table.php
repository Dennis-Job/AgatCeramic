<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('key_hash', 64)->unique();
            $table->string('request_hash', 64);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('expires_at')->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_idempotency_keys');
    }
};
