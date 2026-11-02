<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_group_individual_attributes', function (Blueprint $table): void {
            $table->foreignId('product_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['product_group_id', 'attribute_id']);
            $table->index('attribute_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_group_individual_attributes');
    }
};
