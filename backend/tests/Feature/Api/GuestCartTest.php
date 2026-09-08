<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
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
}
