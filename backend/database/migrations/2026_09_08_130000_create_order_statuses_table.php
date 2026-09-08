<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_terminal')->default(false);
            $table->boolean('sets_completed_at')->default(false);
            $table->timestamps();
        });

        $now = now();
        DB::table('order_statuses')->insert([
            ['code' => 'new', 'name' => 'Новый', 'sort_order' => 10, 'is_active' => true, 'is_terminal' => false, 'sets_completed_at' => false, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'processing', 'name' => 'В обработке', 'sort_order' => 20, 'is_active' => true, 'is_terminal' => false, 'sets_completed_at' => false, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'awaiting_clarification', 'name' => 'Ожидает уточнения', 'sort_order' => 30, 'is_active' => true, 'is_terminal' => false, 'sets_completed_at' => false, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'confirmed', 'name' => 'Подтверждён', 'sort_order' => 40, 'is_active' => true, 'is_terminal' => false, 'sets_completed_at' => false, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'awaiting_payment', 'name' => 'Ожидает оплаты', 'sort_order' => 50, 'is_active' => true, 'is_terminal' => false, 'sets_completed_at' => false, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'paid', 'name' => 'Оплачен', 'sort_order' => 60, 'is_active' => true, 'is_terminal' => false, 'sets_completed_at' => false, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'picking', 'name' => 'Комплектация', 'sort_order' => 70, 'is_active' => true, 'is_terminal' => false, 'sets_completed_at' => false, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'shipped', 'name' => 'Передан в доставку', 'sort_order' => 80, 'is_active' => true, 'is_terminal' => false, 'sets_completed_at' => false, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'completed', 'name' => 'Завершён', 'sort_order' => 90, 'is_active' => true, 'is_terminal' => true, 'sets_completed_at' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'cancelled', 'name' => 'Отменён', 'sort_order' => 100, 'is_active' => true, 'is_terminal' => true, 'sets_completed_at' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_statuses');
    }
};
