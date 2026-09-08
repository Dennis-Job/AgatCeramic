<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_a_cart_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonStructure(['data' => ['identifier', 'created_at', 'updated_at']]);

        $identifier = $response->json('data.identifier');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $identifier);
        $this->assertDatabaseHas('carts', ['token' => $identifier]);
    }

    public function test_guest_recovers_the_same_cart_with_its_identifier(): void
    {
        $cart = Cart::factory()->create();

        $this->withHeader('X-Cart-Token', $cart->token)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.identifier', $cart->token);

        $this->assertSame(1, Cart::query()->count());
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

    public function test_guest_can_add_update_and_remove_cart_items(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['price' => '1990.00', 'stock_quantity' => 5]);

        $created = $this->withHeader('X-Cart-Token', $cart->token)
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.unit_price', '1990.00')
            ->assertJsonPath('data.line_total', '3980.00')
            ->assertJsonPath('data.product.name', $product->name);
        $itemId = $created->json('data.id');

        $this->withHeader('X-Cart-Token', $cart->token)
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk()
            ->assertJsonPath('data.id', $itemId)
            ->assertJsonPath('data.quantity', 3);

        $this->withHeader('X-Cart-Token', $cart->token)
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('data.quantity', 4)
            ->assertJsonPath('data.line_total', '7960.00');

        $this->withHeader('X-Cart-Token', $cart->token)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $itemId)
            ->assertJsonPath('data.items.0.quantity', 4);

        $product->update(['is_active' => false, 'price' => null]);

        $this->withHeader('X-Cart-Token', $cart->token)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price', null)
            ->assertJsonPath('data.items.0.line_total', null);

        $this->withHeader('X-Cart-Token', $cart->token)
            ->deleteJson("/api/v1/cart/items/{$itemId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('cart_items', ['id' => $itemId]);
    }

    public function test_guest_cart_item_writes_require_an_owned_cart_and_an_available_quantity(): void
    {
        $cart = Cart::factory()->create();
        $otherCart = Cart::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 2]);
        $item = CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $inactiveProduct = Product::factory()->create(['is_active' => false, 'stock_quantity' => 10]);

        $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');

        $this->withHeader('X-Cart-Token', $cart->token)
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.product_id.0', 'Товар недоступен в запрошенном количестве.');

        $this->withHeader('X-Cart-Token', $cart->token)
            ->postJson('/api/v1/cart/items', ['product_id' => $inactiveProduct->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.product_id.0', 'Товар недоступен в запрошенном количестве.');

        $largeStockProduct = Product::factory()->create(['stock_quantity' => 10000]);
        $this->withHeader('X-Cart-Token', $cart->token)
            ->postJson('/api/v1/cart/items', ['product_id' => $largeStockProduct->id, 'quantity' => 9999])
            ->assertCreated();
        $this->withHeader('X-Cart-Token', $cart->token)
            ->postJson('/api/v1/cart/items', ['product_id' => $largeStockProduct->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.product_id.0', 'Товар недоступен в запрошенном количестве.');

        $this->withHeader('X-Cart-Token', $otherCart->token)
            ->patchJson("/api/v1/cart/items/{$item->id}", ['quantity' => 1])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');

        $this->withHeader('X-Cart-Token', $otherCart->token)
            ->deleteJson("/api/v1/cart/items/{$item->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');

        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 1]);
    }
}
