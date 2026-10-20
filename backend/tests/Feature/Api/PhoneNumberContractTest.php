<?php

namespace Tests\Feature\Api;

use App\Models\ContactRequest;
use App\Models\Order;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneNumberContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_requests_store_canonical_numbers_and_reject_invalid_numbers(): void
    {
        $this->postJson('/api/v1/callback-requests', ['phone' => '9991234567'])->assertCreated();
        $this->assertDatabaseHas('contact_requests', ['type' => 'callback', 'phone' => '+79991234567']);
        $this->postJson('/api/v1/partner-requests', [
            'name' => 'Тест', 'phone' => '8 (999) 123-45-67', 'email' => 'test@example.test', 'message' => 'Тест',
        ])->assertCreated();
        $this->assertDatabaseHas('contact_requests', ['type' => 'partner', 'phone' => '+79991234567']);

        foreach (['+19991234567', '+7999123456', '9991234567 доб. 1', ['9991234567']] as $phone) {
            $this->postJson('/api/v1/callback-requests', ['phone' => $phone])
                ->assertUnprocessable()->assertJsonValidationErrors('phone', 'error.details');
        }
    }

    public function test_settings_allow_one_canonical_phone_or_empty_list_and_preserve_on_rejection(): void
    {
        $this->admin();
        $this->patchJson('/api/v1/admin/site-settings', ['phones' => ['+7 (999) 123-45-67']])
            ->assertOk()->assertJsonPath('data.phones', ['+79991234567']);
        $this->assertSame(['+79991234567'], SiteSetting::query()->findOrFail(1)->phones);
        $this->patchJson('/api/v1/admin/site-settings', ['phones' => ['9991234567', '9997654321']])
            ->assertUnprocessable()->assertJsonValidationErrors('phones', 'error.details');
        $this->patchJson('/api/v1/admin/site-settings', ['phones' => ['+19991234567']])
            ->assertUnprocessable()->assertJsonValidationErrors('phones.0', 'error.details');
        $this->assertSame(['+79991234567'], SiteSetting::query()->findOrFail(1)->phones);
        $this->patchJson('/api/v1/admin/site-settings', ['phones' => []])->assertOk()->assertJsonPath('data.phones', []);
    }

    public function test_store_create_update_clear_and_omitted_phone(): void
    {
        $this->admin();
        $id = $this->postJson('/api/v1/admin/stores', [
            'name' => 'Тест', 'address' => 'Тест', 'phone' => '+7 (999) 123-45-67',
        ])->assertCreated()->assertJsonPath('data.phone', '+79991234567')->json('data.id');
        $this->assertDatabaseHas('stores', ['id' => $id, 'phone' => '+79991234567']);
        $this->patchJson("/api/v1/admin/stores/{$id}", ['name' => 'Новый'])->assertOk()->assertJsonPath('data.phone', '+79991234567');
        $this->patchJson("/api/v1/admin/stores/{$id}", ['phone' => '8 (999) 765-43-21'])->assertOk()->assertJsonPath('data.phone', '+79997654321');
        $this->patchJson("/api/v1/admin/stores/{$id}", ['phone' => '+19991234567'])->assertUnprocessable();
        $this->patchJson("/api/v1/admin/stores/{$id}", ['phone' => null])->assertOk()->assertJsonPath('data.phone', null);
        $this->assertNull(Store::query()->findOrFail($id)->phone);
    }

    public function test_formatted_search_matches_canonical_contact_and_order_without_breaking_text_search(): void
    {
        $this->admin();
        ContactRequest::factory()->create(['phone' => '+79991234567', 'name' => 'Искомый клиент']);
        ContactRequest::factory()->create(['phone' => '+79997654321']);
        Order::factory()->create(['customer_phone' => '+79991234567', 'customer_name' => 'Искомый клиент']);
        Order::factory()->create(['customer_phone' => '+79997654321']);
        foreach (['contact-requests', 'orders'] as $resource) {
            $this->getJson('/api/v1/admin/'.$resource.'?'.http_build_query(['search' => '+7 (999) 123-45-67']))
                ->assertOk()->assertJsonCount(1, 'data');
            $this->getJson('/api/v1/admin/'.$resource.'?'.http_build_query(['search' => 'Искомый']))
                ->assertOk()->assertJsonCount(1, 'data');
        }
    }

    private function admin(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'administrator')->sole());
        $this->actingAs($user);
    }
}
