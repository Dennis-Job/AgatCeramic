<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_working_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('weekday');
            $table->boolean('is_closed')->default(true);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->unique(['store_id', 'weekday']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE store_working_hours ADD CONSTRAINT store_hours_weekday_range CHECK (weekday BETWEEN 1 AND 7)');
            DB::statement('ALTER TABLE store_working_hours ADD CONSTRAINT store_hours_valid_interval CHECK ((is_closed AND opens_at IS NULL AND closes_at IS NULL) OR (NOT is_closed AND opens_at IS NOT NULL AND closes_at IS NOT NULL AND opens_at < closes_at))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_working_hours');
    }
};
