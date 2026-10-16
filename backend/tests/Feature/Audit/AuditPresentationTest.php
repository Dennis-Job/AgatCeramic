<?php

namespace Tests\Feature\Audit;

use App\Enums\PaymentStatus;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\ContactRequest;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Queries\AuditLogQuery;
use App\Services\AdminUserManagementService;
use App\Services\AuditLogService;
use App\Services\BrandManagementService;
use App\Services\OrderStatusManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuditPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_identity_and_initial_values_survive_rename_and_deletion(): void
    {
        $actor = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Плитка Агат', 'sku' => 'AC-12345', 'price' => '2400.00']);
        $log = app(AuditLogService::class)->record($actor, 'product.created', $product);
        $product->update(['name' => 'Позднее название']);
        $product->delete();

        $data = (new AuditLogResource(app(AuditLogQuery::class)->prepare($log->fresh())))->toArray(Request::create('/'));

        $this->assertSame('Плитка Агат', $data['entity']['name']);
        $this->assertContains(['label' => 'SKU', 'value' => 'AC-12345'], $data['details']);
        $this->assertSame('2400.00', $log->fresh()->metadata['values']['price']);
        $this->assertContains(['label' => 'Действие', 'value' => 'Создан товар: SKU AC-12345 · Плитка Агат'], $data['details']);
        $this->assertContains(['label' => 'Цена', 'value' => '2400.00'], $data['details']);
    }

    public function test_existing_order_status_metadata_is_presented_without_rewriting_history(): void
    {
        $order = Order::factory()->create(['order_number' => 'AC-1048']);
        $log = AuditLog::query()->create([
            'actor_id' => User::factory()->create()->id,
            'action' => 'order.status-changed',
            'entity_type' => Order::class,
            'entity_id' => $order->id,
            'entity_snapshot' => ['order_number' => 'AC-1048'],
            'metadata' => ['from_status' => 'new', 'to_status' => 'processing'],
            'occurred_at' => now(),
        ]);
        $data = (new AuditLogResource(app(AuditLogQuery::class)->prepare($log)))->toArray(Request::create('/'));

        $this->assertSame('Заказ №AC-1048', $data['entity']['name']);
        $this->assertContains(['label' => 'Номер заказа', 'value' => 'AC-1048'], $data['details']);
        $this->assertContains(['label' => 'Статус заказа', 'value' => 'Новый → В обработке'], $data['details']);
        $this->assertSame(['from_status' => 'new', 'to_status' => 'processing'], $log->fresh()->metadata);
    }

    public function test_saved_field_changes_are_readable_and_secrets_remain_masked(): void
    {
        $product = Product::factory()->create();
        $log = app(AuditLogService::class)->record(User::factory()->create(), 'product.updated', $product, [
            'changes' => ['price' => ['before' => '2400.00', 'after' => '2650.00']],
            'password' => 'do-not-store-this',
            'comment' => 'person@example.test +7 (999) 123-45-67',
        ]);
        $data = (new AuditLogResource(app(AuditLogQuery::class)->prepare($log)))->toArray(Request::create('/'));

        $this->assertContains(['label' => 'Цена', 'value' => '2400.00 → 2650.00'], $data['details']);
        $this->assertStringNotContainsString('do-not-store-this', json_encode($data));
        $this->assertStringNotContainsString('person@example.test', json_encode($data));
        $this->assertStringNotContainsString('999', json_encode($data));
    }

    public function test_employee_details_use_historical_role_labels_without_duplicate_fields(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create(['name' => 'Менеджер', 'slug' => 'manager']);
        $user->roles()->attach($role);
        $log = app(AuditLogService::class)->record($user, 'admin-user.created', $user, ['status' => 'active', 'role_ids' => [$role->id]]);
        $role->update(['name' => 'Новое имя роли']);
        $data = (new AuditLogResource(app(AuditLogQuery::class)->prepare($log)))->toArray(Request::create('/'));
        $labels = array_column($data['details'], 'label');

        $this->assertSame(array_values(array_unique($labels)), $labels);
        $this->assertContains(['label' => 'Роли', 'value' => 'manager · Менеджер'], $data['details']);
    }

    public function test_contact_status_is_not_mislabeled_as_an_order_change(): void
    {
        $contact = ContactRequest::factory()->create();
        $log = app(AuditLogService::class)->record(User::factory()->create(), 'contact.status-changed', $contact, ['from_status' => 'new', 'to_status' => 'awaiting_client']);
        $data = (new AuditLogResource(app(AuditLogQuery::class)->prepare($log)))->toArray(Request::create('/'));

        $this->assertContains(['label' => 'Статус обращения', 'value' => 'Новое → Ожидает клиента'], $data['details']);
    }

    public function test_status_only_employee_update_does_not_resolve_unchanged_roles_from_live_data(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create(['name' => 'Original role']);
        $user->roles()->attach($role);
        app(AdminUserManagementService::class)->update(User::factory()->create(), $user, ['status' => 'blocked']);
        $log = AuditLog::query()->where('action', 'admin-user.updated')->sole();
        $role->update(['name' => 'Later renamed role']);
        $data = (new AuditLogResource(app(AuditLogQuery::class)->prepare($log)))->toArray(Request::create('/'));

        $this->assertStringNotContainsString('Later renamed role', json_encode($data['details'], JSON_THROW_ON_ERROR));
        $this->assertContains(['label' => 'Статус', 'value' => 'Активен → Заблокирован'], $data['details']);
    }

    public function test_numeric_business_identifiers_are_not_mistaken_for_phone_numbers(): void
    {
        $product = Product::factory()->create(['sku' => '1234567890123', 'barcode' => '9876543210987']);
        $log = app(AuditLogService::class)->record(User::factory()->create(), 'product.created', $product, ['note' => '+7 (999) 123-45-67']);

        $this->assertSame('1234567890123', $log->metadata['values']['sku']);
        $this->assertSame('9876543210987', $log->metadata['values']['barcode']);
        $this->assertSame('[redacted]', $log->metadata['note']);
    }

    public function test_payment_status_enums_are_saved_and_presented_as_safe_business_values(): void
    {
        $log = app(AuditLogService::class)->record(User::factory()->create(), 'order.payment-registered', Order::factory()->create(), [
            'from_payment_status' => PaymentStatus::NotPaid, 'to_payment_status' => 'paid', 'payment_amount' => '2400.00',
        ]);
        $data = (new AuditLogResource(app(AuditLogQuery::class)->prepare($log)))->toArray(Request::create('/'));

        $this->assertSame('not_paid', $log->metadata['from_payment_status']);
        $this->assertContains(['label' => 'Статус оплаты', 'value' => 'Не оплачен → Оплачен'], $data['details']);
        $this->assertContains(['label' => 'Сумма оплаты', 'value' => '2400.00'], $data['details']);
    }

    public function test_order_status_labels_are_frozen_at_event_time(): void
    {
        $target = OrderStatus::factory()->create(['code' => 'ready_for_pickup', 'name' => 'Готов к выдаче']);
        $order = Order::factory()->create(['status' => 'new']);
        app(OrderStatusManagementService::class)->update(User::factory()->create(), $order, $target->code);
        $log = AuditLog::query()->where('action', 'order.status-changed')->sole();
        $target->update(['name' => 'Позднее название статуса']);
        $data = (new AuditLogResource(app(AuditLogQuery::class)->prepare($log)))->toArray(Request::create('/'));

        $this->assertContains(['label' => 'Статус заказа', 'value' => 'Новый → Готов к выдаче'], $data['details']);
    }

    public function test_created_snapshot_includes_actual_database_defaults(): void
    {
        app(BrandManagementService::class)->create(User::factory()->create(), ['name' => 'Minimal brand', 'slug' => 'minimal-brand']);
        $log = AuditLog::query()->where('action', 'brand.created')->sole();

        $this->assertTrue($log->metadata['values']['is_active']);
    }
}
