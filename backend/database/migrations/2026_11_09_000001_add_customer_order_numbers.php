<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 12)->nullable()->unique();
            $table->unsignedBigInteger('last_order_sequence')->default(0);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('order_number', 64)->change();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('customer_order_sequence')->nullable();
            $table->unique(['customer_id', 'customer_order_sequence']);
        });
    }

    public function down(): void
    {
        // Losing allocated IDs/counters would allow reusing numbers after reinstallation.
        if (DB::table('customers')->where('last_order_sequence', '>', 0)->exists()) {
            throw new RuntimeException('Cannot roll back customer numbering after numbers have been allocated. Preserve the schema and counters.');
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['customer_id', 'customer_order_sequence']);
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'customer_order_sequence']);
            $table->string('order_number', 24)->change();
        });

        Schema::dropIfExists('customers');
    }
};
