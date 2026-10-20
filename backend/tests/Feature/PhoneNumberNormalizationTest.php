<?php

namespace Tests\Feature;

use App\Models\ContactRequest;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneNumberNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_defaults_to_dry_run_and_apply_is_idempotent_without_discarding_problem_data(): void
    {
        $valid = ContactRequest::factory()->create(['phone' => '+7 (999) 123-45-67']);
        $invalid = ContactRequest::factory()->create(['phone' => 'invalid phone']);
        $empty = ContactRequest::factory()->create(['phone' => null]);
        $order = Order::factory()->create(['customer_phone' => '8 (999) 765-43-21']);
        $store = Store::query()->create(['name' => 'Тест', 'address' => 'Тест', 'phone' => '9991112233']);
        SiteSetting::query()->findOrFail(1)->update(['phones' => ['+7 (999) 123-45-67', '+79997654321']]);

        $this->artisan('phones:normalize')->expectsOutputToContain('dry-run')->assertExitCode(1);
        $this->assertSame('+7 (999) 123-45-67', $valid->fresh()->phone);
        $this->artisan('phones:normalize', ['--apply' => true])->expectsOutputToContain('changed=3')->assertExitCode(1);
        $this->assertSame('+79991234567', $valid->fresh()->phone);
        $this->assertSame('+79997654321', $order->fresh()->customer_phone);
        $this->assertSame('+79991112233', $store->fresh()->phone);
        $this->assertSame('invalid phone', $invalid->fresh()->phone);
        $this->assertNull($empty->fresh()->phone);
        $this->assertCount(2, SiteSetting::query()->findOrFail(1)->phones);
        $this->artisan('phones:normalize', ['--apply' => true])->expectsOutputToContain('changed=0')->assertExitCode(1);
    }

    public function test_single_settings_phone_is_normalized_but_empty_settings_remain_empty(): void
    {
        SiteSetting::query()->findOrFail(1)->update(['phones' => ['+7 (999) 123-45-67']]);
        $this->artisan('phones:normalize', ['--apply' => true])->assertSuccessful();
        $this->assertSame(['+79991234567'], SiteSetting::query()->findOrFail(1)->phones);
        SiteSetting::query()->findOrFail(1)->update(['phones' => []]);
        $this->artisan('phones:normalize', ['--apply' => true])->assertSuccessful();
        $this->assertSame([], SiteSetting::query()->findOrFail(1)->phones);
    }
}
