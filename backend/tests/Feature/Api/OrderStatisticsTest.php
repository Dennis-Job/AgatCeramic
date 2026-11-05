<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_counts_use_moscow_creation_cohorts_current_statuses_and_ignore_list_filters(): void
    {
        $this->actingAs($this->orderViewer());
        $this->order('2026-09-30 21:00:00', 'new');
        $this->order('2026-10-31 20:59:59', 'cancelled');
        $this->order('2026-10-15 00:00:00', 'completed');
        $this->order('2026-10-15 00:00:00', 'completed');
        $this->order('2026-10-15 00:00:00', 'processing');
        $this->order('2026-09-30 20:59:59', 'completed');
        $this->order('2026-09-15 00:00:00', 'new');
        $this->order('2026-10-31 21:00:00', 'cancelled');

        $this->getJson('/api/v1/admin/order-statistics?month=2026-10&status=new&payment_status=paid&search=absent&per_page=1')
            ->assertOk()->assertExactJson(['data' => [
                'month' => '2026-10',
                'previous_month' => '2026-09',
                'timezone' => 'Europe/Moscow',
                'metrics' => [
                    'total' => ['count' => 5, 'previous_count' => 2, 'change_percent' => 150],
                    'new' => ['count' => 1, 'previous_count' => 1, 'change_percent' => 0],
                    'completed' => ['count' => 2, 'previous_count' => 1, 'change_percent' => 100],
                    'cancelled' => ['count' => 1, 'previous_count' => 0, 'change_percent' => null],
                ],
            ]]);
    }

    public function test_year_rollover_zero_baselines_negative_changes_and_rounding(): void
    {
        $this->actingAs($this->orderViewer());
        $this->order('2025-12-31 21:00:00', 'new');
        Order::factory()->count(3)->create(['status' => 'completed', 'created_at' => '2025-12-15 00:00:00']);

        $this->getJson('/api/v1/admin/order-statistics?month=2026-01')->assertOk()
            ->assertJsonPath('data.previous_month', '2025-12')
            ->assertJsonPath('data.metrics.total', ['count' => 1, 'previous_count' => 3, 'change_percent' => -66.7])
            ->assertJsonPath('data.metrics.new', ['count' => 1, 'previous_count' => 0, 'change_percent' => null])
            ->assertJsonPath('data.metrics.completed', ['count' => 0, 'previous_count' => 3, 'change_percent' => -100])
            ->assertJsonPath('data.metrics.cancelled', ['count' => 0, 'previous_count' => 0, 'change_percent' => 0]);
        $this->getJson('/api/v1/admin/order-statistics?month=2030-01')->assertOk()
            ->assertJsonPath('data.metrics.total', ['count' => 0, 'previous_count' => 0, 'change_percent' => 0]);
    }

    public function test_leap_february_and_saved_status_changes_are_reflected_without_cache(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $manager = User::factory()->create();
        $manager->roles()->attach(Role::query()->where('slug', 'order-manager')->sole());
        $this->actingAs($manager);
        $order = $this->order('2024-02-29 20:59:59', 'new');
        $this->order('2024-02-29 21:00:00', 'completed');

        $this->getJson('/api/v1/admin/order-statistics?month=2024-02')->assertOk()
            ->assertJsonPath('data.metrics.total.count', 1)
            ->assertJsonPath('data.metrics.new.count', 1)
            ->assertJsonPath('data.metrics.completed.count', 0);
        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'completed'])->assertOk();
        $this->getJson('/api/v1/admin/order-statistics?month=2024-02')->assertOk()
            ->assertJsonPath('data.metrics.total.count', 1)
            ->assertJsonPath('data.metrics.new.count', 0)
            ->assertJsonPath('data.metrics.completed.count', 1);
    }

    #[DataProvider('invalidMonths')]
    public function test_month_is_required_and_strictly_validated(string $query): void
    {
        $this->actingAs($this->orderViewer())->getJson('/api/v1/admin/order-statistics'.$query)
            ->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
    }

    /** @return list<array{string}> */
    public static function invalidMonths(): array
    {
        return [[''], ['?month='], ['?month=2026-2'], ['?month=2026-00'], ['?month=2026-13'], ['?month=2026-02-01'], ['?month=0000-01'], ['?month=1899-12'], ['?month=9999-01'], ['?month[]=2026-10']];
    }

    public function test_statistics_require_orders_view_permission(): void
    {
        $this->getJson('/api/v1/admin/order-statistics?month=2026-10')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/admin/order-statistics?month=2026-10')->assertForbidden();
    }

    private function order(string $createdAt, string $status): Order
    {
        return Order::factory()->create(['created_at' => $createdAt, 'status' => $status]);
    }

    private function orderViewer(): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->where('code', 'orders.view')->sole());
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
