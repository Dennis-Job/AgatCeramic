<?php

namespace Tests\Feature\Api;

use App\Models\Banner;
use App\Models\HomePage;
use App\Models\Media;
use App\Models\Role;
use App\Models\Slider;
use App\Models\User;
use App\Services\MediaManagementService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HomePageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_the_existing_homepage_and_exposes_published_hero_slides(): void
    {
        $this->getJson('/api/v1/home-page')->assertOk()
            ->assertJsonPath('data.categories.items.0.id', 'porcelain')
            ->assertJsonPath('data.hero_slides.0.image_url', '/images/home/hero-porcelain.webp')
            ->assertJsonCount(3, 'data.hero_slides');

        $page = HomePage::query()->findOrFail(1);
        $slider = Slider::query()->findOrFail($page->hero_slider_id);
        $slider->banners()->firstOrFail()->update(['is_published' => false]);
        $this->getJson('/api/v1/home-page')->assertJsonCount(2, 'data.hero_slides');
        $slider->update(['is_published' => false]);
        $this->getJson('/api/v1/home-page')->assertJsonCount(0, 'data.hero_slides');
    }

    public function test_content_manager_can_update_sections_and_public_media_projection(): void
    {
        $manager = $this->userWithRole('content-manager');
        $media = Media::query()->create([
            'kind' => 'image', 'disk' => 'public', 'path' => 'media/about.webp',
            'mime_type' => 'image/webp', 'size' => 12, 'title' => 'О проекте',
        ]);
        $payload = $this->getJson('/api/v1/home-page')->json('data.about');
        $payload['title'] = 'Новая история';
        $payload['image_media_id'] = $media->id;
        $this->actingAs($manager)->patchJson('/api/v1/admin/home-page', ['about' => $payload])
            ->assertOk()->assertJsonPath('data.about.title', 'Новая история')
            ->assertJsonPath('data.about.image_url', '/images/home/materials.webp');
        $this->getJson('/api/v1/home-page')->assertJsonPath('data.about.image_url', Storage::disk('public')->url('media/about.webp'));
        $header = $this->actingAs($manager)->getJson('/api/v1/admin/home-page')->json('data.header');
        unset($header['logo_url']);
        $header['logo_media_id'] = $media->id;
        $this->actingAs($manager)->patchJson('/api/v1/admin/home-page', ['header' => $header])->assertOk();
        $this->getJson('/api/v1/home-page')->assertJsonPath('data.header.logo_url', Storage::disk('public')->url('media/about.webp'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'home-page.updated', 'entity_id' => 1]);

        try {
            app(MediaManagementService::class)->delete($manager, $media);
            self::fail('Referenced home page media must not be deleted.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('media', $exception->errors());
        }
    }

    public function test_update_rejects_unsafe_urls_and_duplicate_category_ids(): void
    {
        $manager = $this->userWithRole('content-manager');
        $promo = $this->getJson('/api/v1/home-page')->json('data.promo');
        $promo['link_url'] = '//evil.example';
        $this->actingAs($manager)->patchJson('/api/v1/admin/home-page', ['promo' => $promo])
            ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['promo.link_url']]]);

        $categories = $this->getJson('/api/v1/home-page')->json('data.categories');
        $categories['items'][1]['id'] = $categories['items'][0]['id'];
        $this->actingAs($manager)->patchJson('/api/v1/admin/home-page', ['categories' => $categories])
            ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['categories.items.0.id']]]);
    }

    public function test_home_page_management_requires_content_permission(): void
    {
        $this->getJson('/api/v1/admin/home-page')->assertUnauthorized();
        $this->actingAs($this->userWithRole('analyst'))->getJson('/api/v1/admin/home-page')->assertForbidden();
        $this->actingAs($this->userWithRole('analyst'))->patchJson('/api/v1/admin/home-page', ['hero_slider_id' => null])->assertForbidden();
        $this->actingAs($this->userWithRole('super-admin'))->getJson('/api/v1/admin/home-page')->assertOk();
    }

    public function test_selected_slider_must_exist_and_unpublished_banner_is_hidden(): void
    {
        $manager = $this->userWithRole('content-manager');
        $this->actingAs($manager)->patchJson('/api/v1/admin/home-page', ['hero_slider_id' => 999999])->assertUnprocessable();
        $banner = Banner::query()->create(['eyebrow' => 'Новый', 'title' => 'Слайд', 'is_published' => false]);
        $slider = Slider::query()->create(['name' => 'Тест', 'slug' => 'test-home', 'is_published' => true]);
        $slider->banners()->attach($banner->id, ['position' => 1]);
        $this->actingAs($manager)->patchJson('/api/v1/admin/home-page', ['hero_slider_id' => $slider->id])
            ->assertOk()->assertJsonCount(0, 'data.hero_slides');
        $banner->update(['is_published' => true]);
        $this->getJson('/api/v1/home-page')->assertJsonPath('data.hero_slides.0.title', 'Слайд');
    }

    public function test_banner_editor_accepts_safe_local_homepage_assets(): void
    {
        $this->actingAs($this->userWithRole('content-manager'))->postJson('/api/v1/admin/banners', [
            'eyebrow' => 'AgatCeramic · Новинка',
            'title' => 'Новый слайд',
            'image_url' => '/images/home/hero-porcelain.webp',
            'image_alt' => 'Керамогранит в интерьере',
            'link_label' => 'Каталог',
            'link_url' => '/#catalog',
        ])->assertCreated()->assertJsonPath('data.image_alt', 'Керамогранит в интерьере')
            ->assertJsonPath('data.link_url', '/#catalog');
    }

    public function test_existing_http_banner_urls_can_be_retained_but_not_added(): void
    {
        $manager = $this->userWithRole('content-manager');
        $banner = Banner::query()->create([
            'title' => 'Старый баннер', 'image_url' => 'http://legacy.example/image.jpg',
            'link_label' => 'Перейти', 'link_url' => 'http://legacy.example/catalog',
        ]);
        $this->actingAs($manager)->patchJson('/api/v1/admin/banners/'.$banner->id, [
            'title' => 'Обновлённый баннер',
            'image_url' => 'http://legacy.example/image.jpg',
            'link_url' => 'http://legacy.example/catalog',
        ])->assertOk();
        $this->actingAs($manager)->patchJson('/api/v1/admin/banners/'.$banner->id, [
            'link_url' => 'http://other.example/catalog',
        ])->assertUnprocessable();
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
