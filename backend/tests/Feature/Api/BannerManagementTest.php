<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_manage_banner_and_only_published_banners_are_public(): void
    {
        $manager = $this->userWithRole('content-manager');
        $created = $this->actingAs($manager)->postJson('/api/v1/admin/banners', [
            'title' => 'Новая коллекция',
            'description' => 'Керамогранит для дома',
            'image_url' => 'https://images.example.test/banner.jpg',
            'link_label' => 'Смотреть',
            'link_url' => 'https://example.test/catalog',
        ])->assertCreated()->assertJsonPath('data.is_published', false);
        $id = $created->json('data.id');

        $this->getJson('/api/v1/banners')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($manager)->getJson('/api/v1/admin/banners')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->actingAs($manager)->patchJson("/api/v1/admin/banners/{$id}", ['is_published' => true])
            ->assertOk()->assertJsonPath('data.is_published', true);
        $this->getJson('/api/v1/banners')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->actingAs($manager)->patchJson("/api/v1/admin/banners/{$id}", ['is_published' => false])->assertOk();
        $this->getJson('/api/v1/banners')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($manager)->deleteJson("/api/v1/admin/banners/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('banners', ['id' => $id]);
        foreach (['banner.created', 'banner.updated', 'banner.deleted'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'entity_id' => $id]);
        }
    }

    public function test_banner_permissions_and_link_validation(): void
    {
        $manager = $this->userWithRole('content-manager');
        $analyst = $this->userWithRole('analyst');
        $this->actingAs($analyst)->getJson('/api/v1/admin/banners')->assertForbidden();
        $this->actingAs($analyst)->postJson('/api/v1/admin/banners', ['title' => 'Нет доступа'])->assertForbidden();
        $this->actingAs($manager)->postJson('/api/v1/admin/banners', [
            'title' => 'Некорректная ссылка', 'link_url' => 'javascript:alert(1)',
        ])->assertUnprocessable();
        $this->actingAs($manager)->postJson('/api/v1/admin/banners', [
            'title' => 'Нет подписи', 'link_url' => 'https://example.test',
        ])->assertUnprocessable();
        $id = $this->actingAs($manager)->postJson('/api/v1/admin/banners', ['title' => 'Баннер'])
            ->assertCreated()->json('data.id');
        $this->actingAs($manager)->patchJson("/api/v1/admin/banners/{$id}", ['link_label' => 'Перейти'])
            ->assertUnprocessable();
        $this->actingAs($analyst)->patchJson("/api/v1/admin/banners/{$id}", ['title' => 'Нет'])->assertForbidden();
        $this->actingAs($analyst)->deleteJson("/api/v1/admin/banners/{$id}")->assertForbidden();
    }

    private function userWithRole(string $slug): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
    }
}
