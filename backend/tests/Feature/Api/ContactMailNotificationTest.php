<?php

namespace Tests\Feature\Api;

use App\Jobs\SendCallbackMail;
use App\Mail\ContactConfirmationMail;
use App\Mail\ContactRequestMail;
use App\Models\CallbackMailDelivery;
use App\Models\ContactRequest;
use App\Models\SiteSetting;
use App\Services\CallbackMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class ContactMailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        SiteSetting::query()->whereKey(1)->update([
            'seller_name' => 'Тестовый магазин', 'address' => 'Москва, Тестовая, 1',
            'phones' => ['+74951204567'], 'email' => 'shop@example.test',
            'partner_email' => 'business@example.test', 'notification_email' => 'staff@example.test',
        ]);
    }

    public function test_partner_form_schedules_two_independent_identifier_only_deliveries(): void
    {
        $this->submit('partner');
        $contact = ContactRequest::query()->sole();
        $this->assertSame(['admin', 'customer'], CallbackMailDelivery::query()->orderBy('id')->pluck('recipient')->all());
        Bus::assertDispatchedTimes(SendCallbackMail::class, 2);
        foreach (CallbackMailDelivery::query()->get() as $delivery) {
            $this->assertSame($contact->id, $delivery->contact_request_id);
            Bus::assertDispatched(SendCallbackMail::class, fn (SendCallbackMail $job): bool => $job->deliveryId === $delivery->id);
        }
    }

    public function test_email_form_also_notifies_staff_and_confirms_to_the_sender(): void
    {
        $this->submit('email');
        $this->sendAll();
        Mail::assertSent(ContactRequestMail::class, fn (ContactRequestMail $mail): bool => $mail->hasTo('staff@example.test'));
        Mail::assertSent(ContactConfirmationMail::class, fn (ContactConfirmationMail $mail): bool => $mail->hasTo('sender@example.test'));
        Mail::assertSentCount(2);
    }

    public function test_partner_mail_contains_safe_contact_details_and_confirmation_keeps_private_address_hidden(): void
    {
        $this->submit('partner');
        $this->sendAll();
        Mail::assertSent(ContactRequestMail::class, function (ContactRequestMail $mail): bool {
            $html = $mail->render();
            foreach (['sender@example.test', '+7 (999) 123-45-67', 'Предложение', '/contacts?contact='] as $value) {
                $this->assertStringContainsString($value, $html);
            }
            $this->assertStringNotContainsString('<script>', $html);

            return $mail->hasTo('staff@example.test');
        });
        Mail::assertSent(ContactConfirmationMail::class, function (ContactConfirmationMail $mail): bool {
            $html = $mail->render();
            foreach (['AgatCeramic', 'Тестовый магазин', 'Москва, Тестовая, 1', '+7 (495) 120-45-67',
                'tel:+74951204567', 'mailto:shop@example.test', 'mailto:business@example.test', 'Номер обращения:'] as $value) {
                $this->assertStringContainsString($value, $html);
            }
            $this->assertStringNotContainsString('staff@example.test', $html);
            $this->assertStringNotContainsString('/contacts?contact=', $html);
            $this->assertStringNotContainsString('<script>', $html);

            return $mail->hasTo('sender@example.test');
        });
        // Duplicate workers cannot resend a completed delivery.
        foreach (CallbackMailDelivery::query()->get() as $delivery) {
            $this->app->make(CallbackMailService::class)->send($delivery->id);
            $this->assertSame('sent', $delivery->fresh()->status);
        }
        Mail::assertSentCount(2);
    }

    public function test_missing_staff_address_does_not_block_customer_and_uses_current_address_on_retry(): void
    {
        SiteSetting::query()->whereKey(1)->update(['notification_email' => null]);
        $this->submit('partner');
        $this->sendAll();
        Mail::assertSentCount(1);
        $delivery = CallbackMailDelivery::query()->where('recipient', 'admin')->sole();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame(0, $delivery->attempts);
        SiteSetting::query()->whereKey(1)->update(['notification_email' => 'new-staff@example.test']);
        $this->app->make(CallbackMailService::class)->send($delivery->id);
        Mail::assertSent(ContactRequestMail::class, fn (ContactRequestMail $mail): bool => $mail->hasTo('new-staff@example.test'));
        Mail::assertSentCount(2);
    }

    public function test_transport_failure_is_retryable_without_resending_the_other_recipient(): void
    {
        $this->submit('partner');
        $service = $this->app->make(CallbackMailService::class);
        $staff = CallbackMailDelivery::query()->where('recipient', 'admin')->sole();
        $customer = CallbackMailDelivery::query()->where('recipient', 'customer')->sole();
        Mail::fake();
        $service->send($staff->id);
        $mailer = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('private transport secret'));
        try {
            $service->send($customer->id);
            $this->fail('Transport errors must remain retryable.');
        } catch (RuntimeException $error) {
            $this->assertStringNotContainsString('secret', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
        $this->assertSame('pending', $customer->fresh()->status);
        $this->assertSame(1, $customer->fresh()->attempts);
        Mail::swap($mailer);
        $service->send($customer->id);
        $service->send($staff->id);
        Mail::assertSentCount(2);
    }

    public function test_anonymized_contacts_are_skipped_for_both_recipients(): void
    {
        $this->submit('partner');
        ContactRequest::query()->update(['name' => null, 'phone' => null, 'email' => null, 'message' => null]);
        $this->sendAll();
        Mail::assertNothingSent();
        $this->assertSame(['skipped', 'skipped'], CallbackMailDelivery::query()->pluck('status')->all());
    }

    public function test_log_mailer_keeps_delivery_pending_without_logging_personal_data(): void
    {
        config(['mail.default' => 'log']);
        $this->submit('partner');
        $this->sendAll();
        Mail::assertNothingSent();
        $this->assertSame(['pending', 'pending'], CallbackMailDelivery::query()->pluck('status')->all());
        $this->assertSame([0, 0], CallbackMailDelivery::query()->pluck('attempts')->all());
    }

    public function test_failover_with_log_cannot_log_personal_data_or_claim_delivery(): void
    {
        config(['mail.default' => 'failover']);
        $this->submit('partner');
        $this->sendAll();
        Mail::assertNothingSent();
        $this->assertSame(['pending', 'pending'], CallbackMailDelivery::query()->pluck('status')->all());
    }

    public function test_dispatch_failure_does_not_lose_the_request_or_either_delivery(): void
    {
        Bus::shouldReceive('dispatch')->andThrow(new RuntimeException('private transport secret'));
        $this->submit('partner');
        $this->assertDatabaseCount('contact_requests', 1);
        $this->assertDatabaseCount('callback_mail_deliveries', 2);
        $this->assertSame(['pending', 'pending'], CallbackMailDelivery::query()->pluck('status')->all());
    }

    private function submit(string $type): void
    {
        $payload = ['name' => '<script>Test</script>', 'email' => 'sender@example.test', 'message' => 'Предложение'];
        if ($type === 'partner') {
            $payload['phone'] = '+79991234567';
        }
        $this->postJson("/api/v1/{$type}-requests", $payload)->assertCreated()->assertContent('');
    }

    private function sendAll(): void
    {
        Mail::fake();
        foreach (CallbackMailDelivery::query()->get() as $delivery) {
            $this->app->make(CallbackMailService::class)->send($delivery->id);
        }
    }
}
