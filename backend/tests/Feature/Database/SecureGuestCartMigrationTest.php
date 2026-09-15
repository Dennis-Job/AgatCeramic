<?php

namespace Tests\Feature\Database;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecureGuestCartMigrationTest extends TestCase
{
    public function test_it_hashes_legacy_tokens_and_expires_existing_carts_by_content(): void
    {
        $originalConnection = DB::getDefaultConnection();
        $originalKey = config('cart.token_hmac_key');
        $originalEmptyTtl = config('cart.empty_ttl_hours');
        $originalAbandonedTtl = config('cart.abandoned_ttl_days');
        config([
            'database.connections.cart_migration_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cart.token_hmac_key' => 'migration-test-key',
            'cart.empty_ttl_hours' => 2,
            'cart.abandoned_ttl_days' => 3,
        ]);
        DB::setDefaultConnection('cart_migration_test');

        try {
            $this->createLegacyTables();
            $emptyToken = str_repeat('a', 64);
            $activeToken = str_repeat('b', 64);
            $updatedAt = '2026-09-01 12:00:00';
            DB::table('carts')->insert([
                ['id' => 1, 'token' => $emptyToken, 'created_at' => $updatedAt, 'updated_at' => $updatedAt],
                ['id' => 2, 'token' => $activeToken, 'created_at' => $updatedAt, 'updated_at' => $updatedAt],
            ]);
            DB::table('cart_items')->insert(['cart_id' => 2]);

            $migration = require database_path('migrations/2026_09_15_120000_secure_guest_cart_tokens_and_add_expiry.php');
            $migration->up();

            $empty = DB::table('carts')->where('id', 1)->sole();
            $active = DB::table('carts')->where('id', 2)->sole();
            $this->assertFalse(Schema::hasColumn('carts', 'token'));
            $this->assertSame(hash_hmac('sha256', $emptyToken, 'migration-test-key'), $empty->token_hash);
            $this->assertSame(hash_hmac('sha256', $activeToken, 'migration-test-key'), $active->token_hash);
            $this->assertSame(
                CarbonImmutable::parse($updatedAt)->addHours(2)->toDateTimeString(),
                CarbonImmutable::parse($empty->expires_at)->toDateTimeString(),
            );
            $this->assertSame(
                CarbonImmutable::parse($updatedAt)->addDays(3)->toDateTimeString(),
                CarbonImmutable::parse($active->expires_at)->toDateTimeString(),
            );
        } finally {
            DB::disconnect('cart_migration_test');
            DB::purge('cart_migration_test');
            DB::setDefaultConnection($originalConnection);
            config([
                'cart.token_hmac_key' => $originalKey,
                'cart.empty_ttl_hours' => $originalEmptyTtl,
                'cart.abandoned_ttl_days' => $originalAbandonedTtl,
            ]);
        }
    }

    private function createLegacyTables(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->string('token', 64)->unique();
            $table->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cart_id');
        });
    }
}
