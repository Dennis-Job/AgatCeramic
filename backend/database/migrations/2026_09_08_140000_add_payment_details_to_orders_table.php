<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('payment_amount', 20, 2)->nullable()->after('payment_status');
            $table->string('payment_method', 100)->nullable()->after('payment_amount');
            $table->string('payment_reference')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_amount', 'payment_method', 'payment_reference']);
        });
    }
};
