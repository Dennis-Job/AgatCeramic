<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('partner_email')->nullable();
            $table->string('notification_email')->nullable();
        });
        // Preserve the existing public address and use it for the requested initial notification destination.
        DB::table('site_settings')->whereNotNull('email')->update(['notification_email' => DB::raw('email')]);
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn(['partner_email', 'notification_email']);
        });
    }
};
