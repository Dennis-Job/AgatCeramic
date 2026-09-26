<?php

namespace Tests\Feature\Api;

use App\Models\Banner;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SliderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_slider_publication_order_and_banner_visibility(): void
    {
        $manager = $this->userWithRole('content-manager');
        $first = Banner::query()->create(['title' => 'Первый', 'is_published' => true]);
        $draft = Banner::query()->create(['title' => 'Черновик']);
        $last = Banner::query()->create(['title' => 'Последний', 'is_published' => true]);

        $created = $this->actingAs($manager)->postJson('/api/v1/admin/sliders', [
            'name' => 'Главная страница', 'slug' => 'home',
            'banner_ids' => [$last->id, $draft->id, $first->id],
        ])->assertCreated()->assertJsonPath('data.is_published', false);
        $id = $created->json('data.id');
        $this->getJson('/api/v1/sliders/home')->assertNotFound();

        $this->actingAs($manager)->patchJson("/api/v1/admin/sliders/{$id}", ['is_published' => true])
            ->assertOk()->assertJsonPath('data.banners.0.id', $last->id)
            ->assertJsonPath('data.banners.1.id', $draft->id);
        $this->getJson('/api/v1/sliders/home')->assertOk()->assertJsonCount(2, 'data.banners')
            ->assertJsonPath('data.banners.0.id', $last->id)
            ->assertJsonPath('data.banners.1.id', $first->id);
        $draft->update(['is_published' => true]);
        $this->getJson('/api/v1/sliders/home')->assertJsonCount(3, 'data.banners')
            ->assertJsonPath('data.banners.1.id', $draft->id);

        $this->actingAs($manager)->patchJson("/api/v1/admin/sliders/{$id}", [
            'banner_ids' => [$first->id, $last->id],
        ])->assertOk()->assertJsonPath('data.banners.0.id', $first->id);
        $this->getJson('/api/v1/sliders/home')->assertJsonPath('data.banners.0.id', $first->id);
        $this->actingAs($manager)->deleteJson("/api/v1/admin/sliders/{$id}")->assertNoContent();
        $this->assertDatabaseCount('banners', 3);
        $this->assertDatabaseCount('slider_banner', 0);
        foreach (['slider.created', 'slider.updated', 'slider.deleted'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'entity_id' => $id]);
        }
    }

    public function test_slider_permissions_validation_and_banner_options(): void
    {
        $manager = $this->userWithRole('content-manager');
        $analyst = $this->userWithRole('analyst');
        $banner = Banner::query()->create(['title' => 'Мозаика', 'is_published' => true]);
        $this->actingAs($analyst)->getJson('/api/v1/admin/sliders')->assertForbidden();
        $this->actingAs($analyst)->getJson('/api/v1/admin/sliders/banner-options')->assertForbidden();
        $this->actingAs($analyst)->postJson('/api/v1/admin/sliders', ['name' => 'Нет', 'slug' => 'no'])->assertForbidden();
        $this->actingAs($manager)->getJson('/api/v1/admin/sliders/banner-options?q='.urlencode('Мозаика'))
            ->assertOk()->assertJsonPath('data.0.id', $banner->id);
        $this->actingAs($manager)->postJson('/api/v1/admin/sliders', [
            'name' => 'Ошибка', 'slug' => 'Bad Slug', 'banner_ids' => [$banner->id, $banner->id],
        ])->assertUnprocessable();
        $this->actingAs($manager)->postJson('/api/v1/admin/sliders', [
            'name' => 'Ошибка', 'slug' => 'duplicate-banners', 'banner_ids' => [$banner->id, $banner->id],
        ])->assertUnprocessable();
        $this->actingAs($manager)->postJson('/api/v1/admin/sliders', [
            'name' => 'Ошибка', 'slug' => 'missing', 'banner_ids' => [999999],
        ])->assertUnprocessable();
        $id = $this->actingAs($manager)->postJson('/api/v1/admin/sliders', [
            'name' => 'Главный', 'slug' => 'home', 'banner_ids' => [$banner->id],
        ])->assertCreated()->json('data.id');
        $this->actingAs($manager)->postJson('/api/v1/admin/sliders', [
            'name' => 'Дубль', 'slug' => 'home',
        ])->assertUnprocessable();
        $this->actingAs($analyst)->patchJson("/api/v1/admin/sliders/{$id}", ['name' => 'Нет'])->assertForbidden();
        $this->actingAs($analyst)->deleteJson("/api/v1/admin/sliders/{$id}")->assertForbidden();
        $this->actingAs($manager)->patchJson("/api/v1/admin/sliders/{$id}", ['name' => 'Новый', 'slug' => 'home'])
            ->assertOk()->assertJsonPath('data.banners.0.id', $banner->id);
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
