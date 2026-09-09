<?php

namespace Tests\Feature\Api;

use App\Jobs\SendOrderConfirmation;
use App\Mail\OrderConfirmationMail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_queues_confirmation_after_commit_only_when_customer_supplies_an_email(): void
    {
        Queue::fake();
        $cart = $this->cartWithProduct();

        $response = $this->withHeader('X-Cart-Token', $cart->token)
            ->withHeader('Idempotency-Key', 'order-confirmation-email-0001')
            ->postJson('/api/v1/orders', $this->checkoutPayload())
            ->assertCreated();

        $order = Order::query()->where('order_number', $response->json('data.order_number'))->sole();
        Queue::assertPushed(SendOrderConfirmation::class, fn (SendOrderConfirmation $job): bool => $job->orderId === $order->id);

        Queue::fake();
        $cartWithoutEmail = $this->cartWithProduct();
        $this->withHeader('X-Cart-Token', $cartWithoutEmail->token)
            ->withHeader('Idempotency-Key', 'order-confirmation-no-email-001')
            ->postJson('/api/v1/orders', $this->checkoutPayload(['customer_email' => null]))
            ->assertCreated();

        Queue::assertNothingPushed();
    }

    public function test_queued_confirmation_uses_immutable_order_item_snapshots(): void
    {
        Mail::fake();
        $order = Order::factory()->create([
            'customer_email' => 'buyer@example.test',
            'order_number' => 'AC-20260908-ABCDEF1234',
            'total_amount' => '2500.00',
        ]);
        $order->items()->create([
            'product_name' => 'Белый мрамор <тест>',
            'product_sku' => '11000001',
            'unit_price' => '1250.00',
            'quantity' => 2,
            'line_total' => '2500.00',
        ]);

        (new SendOrderConfirmation($order->id))->handle();

        Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) use ($order): bool {
            $html = $mail->render();

            return $mail->hasTo('buyer@example.test')
                && str_contains($mail->envelope()->subject, $order->order_number)
                && str_contains($html, $order->order_number)
                && str_contains($html, '2500.00 ₽')
                && str_contains($html, 'Белый мрамор &lt;тест&gt;');
        });
    }

    private function cartWithProduct(): Cart
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['price' => '1250.00', 'stock_quantity' => 2]);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        return $cart;
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function checkoutPayload(array $overrides = []): array
    {
        return [
            'customer_name' => 'Иван Петров',
            'customer_phone' => '+7 (999) 123-45-67',
            'customer_email' => 'buyer@example.test',
            'delivery_address' => 'Москва, улица Пример, дом 1',
            ...$overrides,
        ];
    }
}
