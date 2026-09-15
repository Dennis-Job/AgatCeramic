<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->char('token_hash', 64)->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
        });

        $key = config('cart.token_hmac_key');
        if (! is_string($key) || $key === '') {
            throw new LogicException('Guest cart token HMAC key is required to migrate existing carts.');
        }

        $emptyHours = $this->positiveConfig('cart.empty_ttl_hours');
        $abandonedDays = $this->positiveConfig('cart.abandoned_ttl_days');

        DB::table('carts')->orderBy('id')->chunkById(100, function ($carts) use ($key, $emptyHours, $abandonedDays): void {
            $cartIdsWithItems = DB::table('cart_items')
                ->whereIn('cart_id', $carts->pluck('id'))
                ->distinct()
                ->pluck('cart_id')
                ->all();
            $idsWithItems = [];
            foreach ($cartIdsWithItems as $id) {
                if (! is_int($id) && ! is_string($id)) {
                    throw new LogicException('Legacy cart item contains an invalid cart identifier.');
                }

                $idsWithItems[(int) $id] = true;
            }

            foreach ($carts as $cart) {
                if ((! is_int($cart->id) && ! is_string($cart->id))
                    || (! is_string($cart->updated_at) && ! $cart->updated_at instanceof DateTimeInterface)
                    || ! is_string($cart->token)) {
                    throw new LogicException('Legacy cart contains values that cannot be migrated safely.');
                }

                $cartId = (int) $cart->id;
                $lastActivity = CarbonImmutable::parse($cart->updated_at);
                $expiresAt = isset($idsWithItems[$cartId])
                    ? $lastActivity->addDays($abandonedDays)
                    : $lastActivity->addHours($emptyHours);

                DB::table('carts')->where('id', $cartId)->update([
                    'token_hash' => hash_hmac('sha256', $cart->token, $key),
                    'expires_at' => $expiresAt,
                ]);
            }
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropUnique(['token']);
            $table->dropColumn('token');
        });
        Schema::table('carts', function (Blueprint $table): void {
            $table->char('token_hash', 64)->nullable(false)->change();
            $table->timestamp('expires_at')->nullable(false)->change();
            $table->index(['expires_at', 'id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Guest cart token hashing is irreversible; restore a pre-migration backup instead of rolling back.');
    }

    private function positiveConfig(string $key): int
    {
        $value = filter_var(config($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new LogicException("{$key} must be a positive integer.");
        }

        return $value;
    }
};
