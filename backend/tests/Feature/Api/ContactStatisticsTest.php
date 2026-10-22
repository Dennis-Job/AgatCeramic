<?php

namespace Tests\Feature\Api;

use App\Models\ContactRequest;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContactStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_counts_use_creation_cohorts_current_statuses_and_ignore_list_filters(): void
    {
        $this->actingAs($this->userWithRole('order-manager'));
        $this->contact('2026-09-30 21:00:00', 'partner', 'completed');
        $this->contact('2026-10-31 20:59:59', 'partner', 'rejected');
        $this->contact('2026-10-15 00:00:00', 'callback', 'completed');
        $this->contact('2026-10-15 00:00:00', 'email', 'completed');
        $this->contact('2026-10-15 00:00:00', 'email', 'new');
        $this->contact('2026-09-30 20:59:59', 'partner', 'completed');
        $this->contact('2026-09-15 00:00:00', 'callback', 'rejected');
        $this->contact('2026-10-31 21:00:00', 'callback', 'rejected');

        $this->getJson('/api/v1/admin/contact-statistics?month=2026-10&status=new&type=email&search=absent&unassigned=1')
            ->assertOk()->assertExactJson(['data' => [
                'month' => '2026-10',
                'previous_month' => '2026-09',
                'timezone' => 'Europe/Moscow',
                'metrics' => [
                    'partner' => ['count' => 2, 'previous_count' => 1, 'change_percent' => 100],
                    'callback' => ['count' => 1, 'previous_count' => 1, 'change_percent' => 0],
                    'completed' => ['count' => 3, 'previous_count' => 1, 'change_percent' => 200],
                    'rejected' => ['count' => 1, 'previous_count' => 1, 'change_percent' => 0],
                ],
            ]]);
    }

    public function test_year_rollover_zero_baselines_negative_changes_and_rounding(): void
    {
        $this->actingAs($this->userWithRole('order-manager'));
        $this->contact('2025-12-31 21:00:00', 'partner', 'new');
        $this->contact('2026-01-15 00:00:00', 'callback', 'completed');
        ContactRequest::factory()->count(3)->create([
            'type' => 'callback', 'status' => 'rejected', 'created_at' => '2025-12-15 00:00:00',
        ]);

        $this->getJson('/api/v1/admin/contact-statistics?month=2026-01')->assertOk()
            ->assertJsonPath('data.previous_month', '2025-12')
            ->assertJsonPath('data.metrics.partner', ['count' => 1, 'previous_count' => 0, 'change_percent' => null])
            ->assertJsonPath('data.metrics.callback', ['count' => 1, 'previous_count' => 3, 'change_percent' => -66.7])
            ->assertJsonPath('data.metrics.completed.change_percent', null)
            ->assertJsonPath('data.metrics.rejected.change_percent', -100);
        $this->getJson('/api/v1/admin/contact-statistics?month=2030-01')->assertOk()
            ->assertJsonPath('data.metrics.partner', ['count' => 0, 'previous_count' => 0, 'change_percent' => 0]);
    }

    public function test_leap_february_and_saved_status_changes_are_reflected_without_cache(): void
    {
        $this->actingAs($this->userWithRole('order-manager'));
        $contact = $this->contact('2024-02-29 20:59:59', 'callback', 'processing');
        $this->contact('2024-02-29 21:00:00', 'partner', 'completed');
        $this->getJson('/api/v1/admin/contact-statistics?month=2024-02')->assertOk()
            ->assertJsonPath('data.metrics.callback.count', 1)
            ->assertJsonPath('data.metrics.partner.count', 0)
            ->assertJsonPath('data.metrics.completed.count', 0);
        $this->patchJson("/api/v1/admin/contact-requests/{$contact->id}/status", ['status' => 'completed'])->assertOk();
        $this->getJson('/api/v1/admin/contact-statistics?month=2024-02')->assertOk()
            ->assertJsonPath('data.metrics.completed.count', 1);
    }

    #[DataProvider('invalidMonths')]
    public function test_month_is_required_and_strictly_validated(string $query): void
    {
        $this->actingAs($this->userWithRole('order-manager'))
            ->getJson('/api/v1/admin/contact-statistics'.$query)
            ->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
    }

    /** @return list<array{string}> */
    public static function invalidMonths(): array
    {
        return [[''], ['?month='], ['?month=2026-2'], ['?month=2026-00'], ['?month=2026-13'], ['?month=2026-02-01'], ['?month=0000-01'], ['?month=1899-12'], ['?month=9999-01'], ['?month[]=2026-10']];
    }

    public function test_statistics_require_contacts_view_permission(): void
    {
        $this->getJson('/api/v1/admin/contact-statistics?month=2026-10')->assertUnauthorized();
        $this->actingAs($this->userWithRole('analyst'))->getJson('/api/v1/admin/contact-statistics?month=2026-10')->assertForbidden();
    }

    private function contact(string $createdAt, string $type, string $status): ContactRequest
    {
        return ContactRequest::factory()->create(['created_at' => $createdAt, 'type' => $type, 'status' => $status]);
    }

    private function userWithRole(string $slug): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
    }
}
