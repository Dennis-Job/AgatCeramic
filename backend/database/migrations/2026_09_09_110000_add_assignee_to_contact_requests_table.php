<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->index(['assignee_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->dropIndex(['assignee_id', 'assigned_at']);
            $table->dropConstrainedForeignId('assignee_id');
            $table->dropColumn('assigned_at');
        });
    }
};
