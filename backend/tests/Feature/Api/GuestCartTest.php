<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesGuestCarts;
use Tests\TestCase;

class GuestCartTest extends TestCase
{
    use CreatesGuestCarts;
    use RefreshDatabase;

    public function test_guest_can_create_a_cart_without_storing_its_bearer_token(): void
    {
        $response = $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonStructure(['data' => ['identifier', 'created_at', 'updated_at']]);

        $identifier = $response->json('data.identifier');
        $hash = hash_hmac('sha256', $identifier, (string) config('cart.token_hmac_key'));

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $identifier);
        $this->assertDatabaseHas('carts', ['token_hash' => $hash]);
        $this->assertNotSame($identifier, $hash);
        $this->assertFalse(Schema::hasColumn('carts', 'token'));
    }

    public function test_guest_recovers_only_a_live_cart_owned_by_its_identifier(): void
    {
        $resolved = $this->createGuestCart();

        $this->withHeader('X-Cart-Token', $resolved->token)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.identifier', $resolved->token);

        $this->assertSame(1, Cart::query()->count());

        $resolved->cart->update(['expires_at' => now()->subSecond()]);
        $this->withHeader('X-Cart-Token', $resolved->token)
            ->getJson('/api/v1/cart')
            ->assertNotFound();
    }

    public function test_unknown_or_malformed_cart_identifier_is_not_accepted(): void
    {
        $this->withHeader('X-Cart-Token', bin2hex(random_bytes(32)))
            ->getJson('/api/v1/cart')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');

        $this->withHeader('X-Cart-Token', 'not-a-cart-token')
            ->getJson('/api/v1/cart')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame(0, Cart::query()->count());
    }

    public function test_guest_can_add_update_and_remove_cart_items_with_ttl_transitions(): void
    {
        config(['cart.empty_ttl_hours' => 2, 'cart.abandoned_ttl_days' => 3]);
        $resolved = $this->createGuestCart();
        $cart = $resolved->cart;
        $product = Product::factory()->create(['price' => '1990.00', 'stock_quantity' => 5]);

        $created = $this->withHeader('X-Cart-Token', $resolved->token)
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.unit_price', '1990.00')
            ->assertJsonPath('data.line_total', '3980.00')
            ->assertJsonPath('data.product.name', $product->name);
        $itemId = $created->json('data.id');
        $this->assertTrue($cart->fresh()->expires_at->isSameSecond(now()->addDays((int) config('cart.abandoned_ttl_days'))));

        $this->withHeader('X-Cart-Token', $resolved->token)
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk()
            ->assertJsonPath('data.id', $itemId)
            ->assertJsonPath('data.quantity', 3);

        $this->withHeader('X-Cart-Token', $resolved->token)
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('data.quantity', 4)
            ->assertJsonPath('data.line_total', '7960.00');

        $this->withHeader('X-Cart-Token', $resolved->token)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $itemId)
            ->assertJsonPath('data.items.0.quantity', 4);

        $product->update(['is_active' => false, 'price' => null]);

        $this->withHeader('X-Cart-Token', $resolved->token)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price', null)
            ->assertJsonPath('data.items.0.line_total', null);

        $this->withHeader('X-Cart-Token', $resolved->token)
            ->deleteJson("/api/v1/cart/items/{$itemId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('cart_items', ['id' => $itemId]);
        $this->assertTrue($cart->fresh()->expires_at->isSameSecond(now()->addHours((int) config('cart.empty_ttl_hours'))));
    }

    public function test_guest_cart_item_writes_require_a_live_owned_cart_and_available_quantity(): void
    {
        $resolved = $this->createGuestCart();
        $otherResolved = $this->createGuestCart();
        $cart = $resolved->cart;
        $product = Product::factory()->create(['stock_quantity' => 2]);
        $item = CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $inactiveProduct = Product::factory()->create(['is_active' => false, 'stock_quantity' => 10]);

        $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertUnprocessable();
        $this->withHeader('X-Cart-Token', $resolved->token)
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertUnprocessable();
        $this->withHeader('X-Cart-Token', $resolved->token)
            ->postJson('/api/v1/cart/items', ['product_id' => $inactiveProduct->id, 'quantity' => 1])
            ->assertUnprocessable();

        $largeStockProduct = Product::factory()->create(['stock_quantity' => 10000]);
        $this->withHeader('X-Cart-Token', $resolved->token)
            ->postJson('/api/v1/cart/items', ['product_id' => $largeStockProduct->id, 'quantity' => 9999])
            ->assertCreated();
        $this->withHeader('X-Cart-Token', $resolved->token)
            ->postJson('/api/v1/cart/items', ['product_id' => $largeStockProduct->id, 'quantity' => 1])
            ->assertUnprocessable();

        $this->withHeader('X-Cart-Token', $otherResolved->token)
            ->patchJson("/api/v1/cart/items/{$item->id}", ['quantity' => 1])
            ->assertNotFound();
        $this->withHeader('X-Cart-Token', $otherResolved->token)
            ->deleteJson("/api/v1/cart/items/{$item->id}")
            ->assertNotFound();

        $cart->update(['expires_at' => now()->subSecond()]);
        $this->withHeader('X-Cart-Token', $resolved->token)
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertNotFound();
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 1]);
    }

    public function test_cleanup_is_bounded_idempotent_and_visible_to_operations(): void
    {
        Cart::factory()->count(3)->create(['expires_at' => now()->subMinute()]);
        $live = Cart::factory()->create(['expires_at' => now()->addMinute()]);

        $this->assertSame(1, Artisan::call('cart:prune', ['--limit' => 0]));
        $this->assertSame(4, Cart::query()->count());
        $this->assertSame(0, Artisan::call('cart:prune', ['--limit' => 2]));
        $this->assertStringContainsString('Deleted 2 expired guest cart(s); 1 remain eligible.', Artisan::output());
        $this->assertDatabaseHas('carts', ['id' => $live->id]);
        $this->assertSame(2, Cart::query()->count());

        $this->assertSame(0, Artisan::call('cart:prune', ['--limit' => 2]));
        $this->assertSame(1, Cart::query()->count());
        $this->assertSame(0, Artisan::call('cart:prune', ['--limit' => 2]));
        $this->assertSame(1, Cart::query()->count());
    }

    public function test_scheduler_registers_guest_cart_cleanup(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(static fn ($event): string => (string) $event->command);

        $this->assertTrue($commands->contains(static fn (string $command): bool => str_contains($command, 'cart:prune')));
    }
}
