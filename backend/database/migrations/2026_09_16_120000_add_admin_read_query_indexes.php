<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['action', 'occurred_at']);
            $table->index(['actor_id', 'occurred_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['status', 'created_at']);
            $table->index(['payment_status', 'created_at']);
        });

        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->index(['assignee_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index(['status', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['status', 'name']);
        });

        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->dropIndex(['assignee_id', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['payment_status', 'created_at']);
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['action', 'occurred_at']);
            $table->dropIndex(['actor_id', 'occurred_at']);
        });
    }
};
