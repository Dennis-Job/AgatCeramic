<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table): void {
            $table->boolean('is_visible_on_product_page')->default(true)->after('is_required');
        });

        // `number` was the pre-TASK-041M name for an unrestricted decimal value.
        // Its validator also accepted JSON strings, so normalize those before making decimal strict.
        $legacyNumberIds = DB::table('attributes')->where('type', 'number')->pluck('id')->all();
        $this->normalizeLegacyNumericStrings('product_attribute_values', $legacyNumberIds);
        $this->normalizeLegacyNumericStrings('product_variant_attribute_values', $legacyNumberIds);
        DB::table('attributes')->whereIn('id', $legacyNumberIds)->update(['type' => 'decimal']);
    }

    public function down(): void
    {
        DB::table('attributes')->where('type', 'decimal')->update(['type' => 'number']);

        Schema::table('attributes', function (Blueprint $table): void {
            $table->dropColumn('is_visible_on_product_page');
        });
    }

    /** @param array<int, int> $attributeIds */
    private function normalizeLegacyNumericStrings(string $table, array $attributeIds): void
    {
        if ($attributeIds === []) {
            return;
        }

        DB::table($table)->whereIn('attribute_id', $attributeIds)->orderBy('id')->lazyById()
            ->each(function (object $row) use ($table): void {
                $decoded = is_string($row->value) ? json_decode($row->value, true) : $row->value;

                if (! is_string($decoded) || ! is_numeric($decoded)) {
                    return;
                }

                $number = filter_var($decoded, FILTER_VALIDATE_INT) !== false ? (int) $decoded : (float) $decoded;
                DB::table($table)->where('id', $row->id)->update(['value' => json_encode($number, JSON_THROW_ON_ERROR)]);
            });
    }
};
