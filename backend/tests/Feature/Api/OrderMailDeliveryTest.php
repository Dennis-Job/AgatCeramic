<?php

namespace Tests\Feature\Api;

use App\Jobs\SendOrderMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderNotificationMail;
use App\Models\Order;
use App\Models\OrderMailDelivery;
use App\Models\SiteSetting;
use App\Services\OrderConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class OrderMailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Mail::fake();
        SiteSetting::query()->whereKey(1)->update([
            'notification_email' => 'staff@example.test', 'email' => 'shop@example.test',
        ]);
    }

    public function test_new_order_schedules_two_independent_identifier_only_deliveries_and_repeated_schedule_is_safe(): void
    {
        $order = $this->schedule();
        $this->app->make(OrderConfirmationService::class)->queue($order);
        $this->assertSame(['admin', 'customer'], OrderMailDelivery::query()->orderBy('id')->pluck('recipient')->all());
        Bus::assertDispatchedTimes(SendOrderMail::class, 2);
        foreach (OrderMailDelivery::query()->get() as $delivery) {
            Bus::assertDispatched(SendOrderMail::class, fn (SendOrderMail $job): bool => $job->deliveryId === $delivery->id);
            $payload = serialize(new SendOrderMail($delivery->id));
            foreach ([$order->customer_name, $order->customer_phone, $order->customer_email, $order->delivery_address] as $private) {
                $this->assertStringNotContainsString($private, $payload);
            }
        }
    }

    public function test_missing_customer_email_still_notifies_the_site(): void
    {
        $this->schedule(['customer_email' => null]);
        $this->sendAll();
        $this->assertSame(['admin'], OrderMailDelivery::query()->pluck('recipient')->all());
        Mail::assertSent(OrderNotificationMail::class, fn ($mail): bool => $mail->hasTo('staff@example.test'));
        Mail::assertNotSent(OrderConfirmationMail::class);
    }

    public function test_both_recipients_are_sent_once_even_when_workers_repeat(): void
    {
        $this->schedule();
        $this->sendAll();
        $this->sendAll();
        Mail::assertSent(OrderNotificationMail::class, fn ($mail): bool => $mail->hasTo('staff@example.test'));
        Mail::assertSent(OrderConfirmationMail::class, fn ($mail): bool => $mail->hasTo('buyer@example.test'));
        Mail::assertSentCount(2);
        $this->assertSame(['sent', 'sent'], OrderMailDelivery::query()->pluck('status')->all());
    }

    public function test_missing_site_address_does_not_block_customer_and_retry_reads_current_settings(): void
    {
        SiteSetting::query()->whereKey(1)->update(['notification_email' => null]);
        $this->schedule();
        $this->sendAll();
        Mail::assertSentCount(1);
        $staff = OrderMailDelivery::query()->where('recipient', 'admin')->sole();
        $this->assertSame('pending', $staff->status);
        $this->assertSame(0, $staff->attempts);
        SiteSetting::query()->whereKey(1)->update(['notification_email' => 'new-staff@example.test']);
        $this->app->make(OrderConfirmationService::class)->send($staff->id);
        Mail::assertSent(OrderNotificationMail::class, fn ($mail): bool => $mail->hasTo('new-staff@example.test'));
        Mail::assertSentCount(2);
    }

    public function test_failure_is_sanitized_and_does_not_repeat_the_successful_other_recipient(): void
    {
        $this->schedule();
        $service = $this->app->make(OrderConfirmationService::class);
        $staff = OrderMailDelivery::query()->where('recipient', 'admin')->sole();
        $customer = OrderMailDelivery::query()->where('recipient', 'customer')->sole();
        $service->send($staff->id);
        $mailer = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('buyer@example.test SMTP secret'));
        try {
            $service->send($customer->id);
            $this->fail('Failed delivery must remain retryable.');
        } catch (RuntimeException $error) {
            $this->assertSame('Order email delivery failed.', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
        $this->assertSame('pending', $customer->fresh()->status);
        $this->assertSame(1, $customer->fresh()->attempts);
        Mail::swap($mailer);
        $this->sendAll();
        Mail::assertSentCount(2);
    }

    public function test_dispatch_failure_keeps_durable_intent_and_does_not_throw_after_order_commit(): void
    {
        Bus::shouldReceive('dispatch')->andThrow(new RuntimeException('private queue password'));
        $this->schedule();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_mail_deliveries', 2);
        $this->assertSame(['pending', 'pending'], OrderMailDelivery::query()->pluck('status')->all());
    }

    public function test_rollback_removes_order_and_deliveries_without_dispatch(): void
    {
        DB::beginTransaction();
        try {
            $this->schedule();
            Bus::assertNothingDispatched();
        } finally {
            DB::rollBack();
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_mail_deliveries', 0);
        Bus::assertNothingDispatched();
    }

    public function test_log_and_nested_failover_mailers_cannot_log_order_personal_data(): void
    {
        $this->schedule();
        config(['mail.default' => 'log']);
        $this->sendAll();
        config(['mail.default' => 'nested', 'mail.mailers.nested' => ['transport' => 'roundrobin', 'mailers' => ['smtp', 'failover']]]);
        $this->sendAll();
        Mail::assertNothingSent();
        $this->assertSame([0, 0], OrderMailDelivery::query()->pluck('attempts')->all());
    }

    public function test_anonymized_order_is_skipped_and_deleted_order_cascades_deliveries(): void
    {
        $order = $this->schedule();
        $order->update(['anonymized_at' => now(), 'customer_name' => null, 'customer_email' => null]);
        $this->sendAll();
        Mail::assertNothingSent();
        $this->assertSame(['skipped', 'skipped'], OrderMailDelivery::query()->pluck('status')->all());
        $order->delete();
        $this->assertDatabaseCount('order_mail_deliveries', 0);
    }

    public function test_recovery_only_dispatches_due_pending_deliveries_and_can_reset_exhaustion(): void
    {
        $this->schedule();
        Bus::fake();
        OrderMailDelivery::query()->update(['last_dispatched_at' => now()->subMinutes(6)]);
        $this->artisan('orders:retry-mail')->assertSuccessful();
        Bus::assertDispatchedTimes(SendOrderMail::class, 2);
        Bus::fake();
        $this->artisan('orders:retry-mail')->assertSuccessful();
        Bus::assertNothingDispatched();
        OrderMailDelivery::query()->where('recipient', 'admin')->update(['attempts' => 9]);
        $this->artisan('orders:retry-mail --retry-exhausted')->assertSuccessful();
        Bus::assertDispatchedTimes(SendOrderMail::class, 1);
        $this->assertSame(0, OrderMailDelivery::query()->where('recipient', 'admin')->sole()->attempts);
    }

    public function test_recovery_advances_through_more_than_one_batch_even_when_site_mailbox_is_missing(): void
    {
        SiteSetting::query()->whereKey(1)->update(['notification_email' => null]);
        $orders = Order::factory()->count(101)->create();
        foreach ($orders as $order) {
            OrderMailDelivery::query()->create([
                'order_id' => $order->id, 'recipient' => 'admin', 'last_dispatched_at' => now()->subMinutes(6),
            ]);
        }
        $customer = OrderMailDelivery::query()->create([
            'order_id' => $orders->first()->id, 'recipient' => 'customer', 'last_dispatched_at' => now()->subMinutes(6),
        ]);
        $this->artisan('orders:retry-mail')->assertSuccessful();
        Bus::assertDispatchedTimes(SendOrderMail::class, 100);
        Bus::assertNotDispatched(SendOrderMail::class, fn ($job): bool => $job->deliveryId === $customer->id);
        Bus::fake();
        $this->travel(6)->minutes();
        $this->artisan('orders:retry-mail')->assertSuccessful();
        Bus::assertDispatched(SendOrderMail::class, fn ($job): bool => $job->deliveryId === $customer->id);
    }

    /** @param array<string, mixed> $attributes */
    private function schedule(array $attributes = []): Order
    {
        return DB::transaction(function () use ($attributes): Order {
            $order = Order::factory()->create(['customer_email' => 'buyer@example.test', ...$attributes]);
            $this->app->make(OrderConfirmationService::class)->queue($order);

            return $order;
        });
    }

    private function sendAll(): void
    {
        foreach (OrderMailDelivery::query()->orderBy('id')->get() as $delivery) {
            $this->app->make(OrderConfirmationService::class)->send($delivery->id);
        }
    }
}
