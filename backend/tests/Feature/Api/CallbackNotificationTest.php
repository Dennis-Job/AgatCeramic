<?php

namespace Tests\Feature\Api;

use App\Jobs\SendCallbackMail;
use App\Mail\CallbackRequestMail;
use App\Models\CallbackMailDelivery;
use App\Models\ContactRequest;
use App\Services\CallbackMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class CallbackNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.callback_admin_address' => 'admin@example.test']);
        Bus::fake();
    }

    public function test_storefront_can_submit_without_a_session_csrf_token(): void
    {
        $this->app['env'] = 'local'; // Exercise real CSRF middleware, normally bypassed by Laravel tests.
        config(['sanctum.stateful' => ['localhost:3000', 'localhost:5173']]);

        $this->withHeaders(['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/'])
            ->postJson('/api/v1/callback-requests', ['phone' => '+79991234567'])
            ->assertCreated();

        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'])
            ->postJson('/api/v1/admin/auth/login', ['email' => 'admin@example.test', 'password' => 'test-password'])
            ->assertStatus(419);
    }

    public function test_callback_queues_only_admin_delivery_without_collecting_customer_email(): void
    {
        $this->postJson('/api/v1/callback-requests', [
            'phone' => '8 (999) 123-45-67', 'name' => '  Клиент  ',
        ])->assertCreated()->assertContent('');

        $this->assertDatabaseHas('contact_requests', [
            'phone' => '+79991234567', 'name' => 'Клиент', 'email' => null,
        ]);
        $this->assertDatabaseCount('callback_mail_deliveries', 1);
        $this->assertDatabaseHas('callback_mail_deliveries', ['recipient' => 'admin']);
        Bus::assertDispatchedTimes(SendCallbackMail::class, 1);
    }

    public function test_customer_email_is_rejected_even_if_valid(): void
    {
        $this->postJson('/api/v1/callback-requests', ['phone' => '+79991234567', 'email' => 'customer@example.test'])
            ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['email']]]);
        $this->assertDatabaseCount('contact_requests', 0);
    }

    public function test_dispatch_failure_keeps_request_and_delivery_for_recovery(): void
    {
        Bus::shouldReceive('dispatch')->andThrow(new RuntimeException('sensitive transport details'));
        $this->postJson('/api/v1/callback-requests', ['phone' => '+79991234567'])->assertCreated();
        $this->assertDatabaseCount('contact_requests', 1);
        $this->assertDatabaseHas('callback_mail_deliveries', ['sent_at' => null]);
    }

    public function test_successful_deliveries_are_not_sent_twice_and_contain_safe_html(): void
    {
        Mail::fake();
        $contact = ContactRequest::factory()->create([
            'type' => 'callback', 'name' => '<script>alert(1)</script>', 'phone' => '+79991234567',
            'email' => 'customer@example.test',
        ]);
        $delivery = CallbackMailDelivery::query()->create(['contact_request_id' => $contact->id, 'recipient' => 'admin']);
        $job = new SendCallbackMail($delivery->id);
        $job->handle($this->app->make(CallbackMailService::class));
        $job->handle($this->app->make(CallbackMailService::class));
        $this->assertNotNull($delivery->fresh()->sent_at);
        Mail::assertSentCount(1);
        Mail::assertSent(CallbackRequestMail::class, function (CallbackRequestMail $mail): bool {
            $html = $mail->render();
            $this->assertStringNotContainsString('<script>', $html);
            $this->assertStringContainsString('+7 (999) 123-45-67', $html);

            return $mail->hasTo('admin@example.test');
        });
        Mail::assertNotSent(CallbackRequestMail::class, fn (CallbackRequestMail $mail): bool => $mail->hasTo('customer@example.test'));
    }

    public function test_legacy_customer_delivery_is_skipped_and_never_sends_mail(): void
    {
        Mail::fake();
        $contact = ContactRequest::factory()->create(['type' => 'callback', 'email' => 'customer@example.test']);
        $delivery = CallbackMailDelivery::query()->create(['contact_request_id' => $contact->id, 'recipient' => 'customer']);
        (new SendCallbackMail($delivery->id))->handle($this->app->make(CallbackMailService::class));
        Mail::assertNothingSent();
        $this->assertSame('skipped', $delivery->fresh()->status);
    }

    public function test_transport_errors_are_sanitized_and_delivery_can_be_retried(): void
    {
        $contact = ContactRequest::factory()->create(['type' => 'callback']);
        $delivery = CallbackMailDelivery::query()->create(['contact_request_id' => $contact->id, 'recipient' => 'admin']);
        $manager = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('private@example.test secret-password'));
        try {
            (new SendCallbackMail($delivery->id))->handle($this->app->make(CallbackMailService::class));
            $this->fail('Transport failure must be retried.');
        } catch (RuntimeException $error) {
            $this->assertSame('Callback email delivery failed.', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
        $this->assertNull($delivery->fresh()->sent_at);
        Mail::swap($manager);
        Mail::fake();
        (new SendCallbackMail($delivery->id))->handle($this->app->make(CallbackMailService::class));
        Mail::assertSentCount(1);
    }

    public function test_deleted_contacts_do_not_send_mail_and_pending_deliveries_can_be_redispatched(): void
    {
        Mail::fake();
        $contact = ContactRequest::factory()->create(['type' => 'callback']);
        $delivery = CallbackMailDelivery::query()->create(['contact_request_id' => $contact->id, 'recipient' => 'admin']);
        $this->artisan('callbacks:retry-mail')->assertSuccessful();
        Bus::assertDispatched(SendCallbackMail::class);
        $contact->delete();
        (new SendCallbackMail($delivery->id))->handle($this->app->make(CallbackMailService::class));
        Mail::assertNothingSent();
        $this->assertDatabaseCount('callback_mail_deliveries', 0);
    }

    public function test_exhausted_deliveries_require_explicit_recovery_and_success_is_never_reset(): void
    {
        Mail::fake();
        $contact = ContactRequest::factory()->create(['type' => 'callback']);
        $delivery = CallbackMailDelivery::query()->create([
            'contact_request_id' => $contact->id, 'recipient' => 'admin', 'attempts' => 9,
        ]);
        (new SendCallbackMail($delivery->id))->handle($this->app->make(CallbackMailService::class));
        Mail::assertNothingSent();
        $this->artisan('callbacks:retry-mail')->assertSuccessful();
        Bus::assertNotDispatched(SendCallbackMail::class);
        $this->artisan('callbacks:retry-mail --retry-exhausted')->assertSuccessful();
        Bus::assertDispatched(SendCallbackMail::class);
        $this->assertSame(0, $delivery->fresh()->attempts);
        (new SendCallbackMail($delivery->id))->handle($this->app->make(CallbackMailService::class));
        $this->artisan('callbacks:retry-mail --retry-exhausted')->assertSuccessful();
        (new SendCallbackMail($delivery->id))->handle($this->app->make(CallbackMailService::class));
        Mail::assertSentCount(1);
    }
}
