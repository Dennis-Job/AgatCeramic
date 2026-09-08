<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_manager_can_register_a_full_manual_payment_without_changing_order_status(): void
    {
        $manager = $this->userWithRole('order-manager');
        $order = Order::factory()->create(['status' => 'awaiting_payment', 'total_amount' => '1250.00']);

        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/orders/{$order->id}/payment", $this->paymentPayload())
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.payment_amount', '1250.00')
            ->assertJsonPath('data.payment_method', 'Банковский перевод')
            ->assertJsonPath('data.payment_reference', 'INV-2026-42');

        $order->refresh();
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('1250.00', $order->payment_amount);
        $this->assertNotNull($order->paid_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $manager->id,
            'action' => 'order.payment-registered',
            'entity_type' => Order::class,
            'entity_id' => $order->id,
        ]);
    }

    public function test_payment_amount_is_checked_against_the_current_order_total(): void
    {
        $manager = $this->userWithRole('order-manager');
        $order = Order::factory()->create(['total_amount' => '1250.00']);

        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/orders/{$order->id}/payment", $this->paymentPayload(['payment_amount' => '1200.00']))
            ->assertUnprocessable()
            ->assertJsonPath('error.details.payment_amount.0', 'Сумма полной оплаты должна совпадать с итоговой суммой заказа.');

        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/orders/{$order->id}/payment", $this->paymentPayload([
                'payment_status' => 'partially_paid',
                'payment_amount' => '1250.00',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('error.details.payment_amount.0', 'Сумма частичной оплаты должна быть больше нуля и меньше итоговой суммы заказа.');

        $this->assertSame(PaymentStatus::NotPaid, $order->fresh()->payment_status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_unpaid_and_pending_states_cannot_include_payment_details(): void
    {
        $manager = $this->userWithRole('order-manager');
        $order = Order::factory()->create();

        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/orders/{$order->id}/payment", [
                'payment_status' => 'pending',
                'payment_amount' => '1.00',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['payment_amount']]]);

        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/orders/{$order->id}/payment", ['payment_status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.payment_amount', null)
            ->assertJsonPath('data.paid_at', null);
    }

    public function test_payment_management_requires_its_own_permission_and_rejects_order_field_mutations(): void
    {
        $analyst = $this->userWithRole('analyst');
        $manager = $this->userWithRole('order-manager');
        $order = Order::factory()->create(['total_amount' => '1250.00']);

        $this->actingAs($analyst)
            ->patchJson("/api/v1/admin/orders/{$order->id}/payment", $this->paymentPayload())
            ->assertForbidden();

        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/orders/{$order->id}/payment", $this->paymentPayload(['status' => 'paid']))
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['status']]]);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function paymentPayload(array $overrides = []): array
    {
        return [
            'payment_status' => 'paid',
            'payment_amount' => '1250.00',
            'payment_method' => 'Банковский перевод',
            'payment_reference' => 'INV-2026-42',
            'paid_at' => now()->subMinute()->toAtomString(),
            ...$overrides,
        ];
    }

    private function userWithRole(string $slug): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
    }
}
