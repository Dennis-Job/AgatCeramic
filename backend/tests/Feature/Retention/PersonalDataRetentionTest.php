<?php

namespace Tests\Feature\Retention;

use App\Models\ContactRequest;
use App\Models\ContactRequestComment;
use App\Models\ContactRequestStatusHistory;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\RetentionLegalHold;
use App\Services\Retention\RetentionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class PersonalDataRetentionTest extends TestCase
{
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-14 12:00:00');
        $this->configureAcceptedPolicy();
    }

    #[\Override]
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_apply_is_blocked_until_policy_is_accepted_and_explicitly_enabled(): void
    {
        config()->set('retention.policy_status', 'proposed');
        config()->set('retention.apply_enabled', false);
        $this->refreshPolicy();
        $order = $this->expiredOrder(['customer_name' => 'Blocked Person']);

        $exitCode = Artisan::call('retention:orders', ['--apply' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertSame('Blocked Person', $order->fresh()?->customer_name);
        $this->assertStringContainsString('policy_not_accepted', $output);
        $this->assertStringNotContainsString('Blocked Person', $output);
        $this->assertDatabaseHas('retention_executions', [
            'scope' => 'orders',
            'status' => 'failed',
            'failure_code' => 'policy_not_accepted',
        ]);
    }

    public function test_order_dry_run_respects_boundary_and_never_outputs_or_mutates_pii(): void
    {
        $cutoff = CarbonImmutable::now()->subYears(3)->subDays(30);
        $expired = $this->expiredOrder([
            'customer_name' => 'Expired Person',
            'customer_email' => 'expired@example.test',
            'completed_at' => $cutoff->subSecond(),
        ]);
        $boundary = $this->expiredOrder([
            'customer_name' => 'Boundary Person',
            'completed_at' => $cutoff,
        ]);

        $this->assertSame(0, Artisan::call('retention:orders'));
        $output = Artisan::output();

        $this->assertSame('Expired Person', $expired->fresh()?->customer_name);
        $this->assertSame('Boundary Person', $boundary->fresh()?->customer_name);
        $this->assertStringContainsString('"action":"direct-pii-expiry"', $output);
        $this->assertStringContainsString('"eligible":1', $output);
        $this->assertStringNotContainsString('Expired Person', $output);
        $this->assertStringNotContainsString('expired@example.test', $output);
    }

    public function test_order_apply_is_bounded_idempotent_honours_hold_and_reconciles_children(): void
    {
        config()->set('retention.batch_size', 1);
        $this->refreshPolicy();
        $first = $this->expiredOrder(['customer_name' => 'First Person']);
        $second = $this->expiredOrder(['customer_name' => 'Second Person']);
        $held = $this->expiredOrder(['customer_name' => 'Held Person']);
        OrderItem::factory()->for($first)->create();
        OrderComment::factory()->for($first)->create(['body' => 'Private comment']);
        OrderStatusHistory::factory()->for($first)->create([
            'to_status' => 'completed',
            'actor_snapshot' => ['name' => 'Private Employee'],
            'occurred_at' => CarbonImmutable::now()->subYears(4),
        ]);
        RetentionLegalHold::query()->create([
            'scope' => 'orders',
            'record_id' => $held->id,
            'case_reference' => 'CASE-1',
            'legal_basis' => 'Litigation hold',
            'owner_reference' => 'legal-team',
            'started_at' => now()->subDay(),
            'review_at' => now()->addDays(30),
        ]);

        $this->assertSame(0, Artisan::call('retention:orders', ['--apply' => true]));
        $this->assertNull($first->fresh()?->customer_name);
        $this->assertSame('Second Person', $second->fresh()?->customer_name);
        $this->assertDatabaseMissing('order_comments', ['order_id' => $first->id]);
        $this->assertDatabaseHas('order_items', ['order_id' => $first->id]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $first->id,
            'actor_id' => null,
            'actor_snapshot' => null,
        ]);

        $this->assertSame(0, Artisan::call('retention:orders', ['--apply' => true]));
        $this->assertNull($second->fresh()->customer_name);
        $this->assertSame('Held Person', $held->fresh()?->customer_name);
        $this->assertSame(2, DB::table('retention_tombstones')->where('action', 'order-anonymized')->count());

        $this->assertSame(0, Artisan::call('retention:orders', ['--apply' => true]));
        $this->assertSame(2, DB::table('retention_tombstones')->where('action', 'order-anonymized')->count());
    }

    public function test_missing_order_terminal_timestamp_is_flagged_without_deletion(): void
    {
        $order = Order::factory()->create([
            'status' => 'cancelled',
            'completed_at' => null,
            'paid_at' => null,
        ]);

        $this->assertSame(0, Artisan::call('retention:orders', ['--apply' => true]));

        $this->assertModelExists($order);
        $this->assertDatabaseHas('retention_exceptions', [
            'scope' => 'orders',
            'record_id' => $order->id,
            'reason_code' => 'terminal_timestamp_missing',
        ]);
    }

    public function test_contact_apply_deletes_terminal_parent_and_children_but_preserves_boundary_and_hold(): void
    {
        $completedCutoff = CarbonImmutable::now()->subDays(210);
        $expired = ContactRequest::factory()->create([
            'status' => 'completed',
            'completed_at' => $completedCutoff->subSecond(),
            'name' => 'Expired Contact',
        ]);
        ContactRequestComment::factory()->for($expired)->create(['body' => 'Sensitive text']);
        ContactRequestStatusHistory::factory()->for($expired)->create(['to_status' => 'completed']);
        $boundary = ContactRequest::factory()->create([
            'status' => 'completed',
            'completed_at' => $completedCutoff,
        ]);
        $held = ContactRequest::factory()->create([
            'status' => 'rejected',
            'completed_at' => CarbonImmutable::now()->subDays(61),
        ]);
        RetentionLegalHold::query()->create([
            'scope' => 'contacts',
            'record_id' => $held->id,
            'case_reference' => 'CASE-2',
            'legal_basis' => 'Incident review',
            'owner_reference' => 'privacy-team',
            'started_at' => now()->subDay(),
            'review_at' => now()->addDays(30),
        ]);

        $this->assertSame(0, Artisan::call('retention:contacts', ['--apply' => true]));
        $output = Artisan::output();

        $this->assertModelMissing($expired);
        $this->assertDatabaseMissing('contact_request_comments', ['contact_request_id' => $expired->id]);
        $this->assertDatabaseMissing('contact_request_status_histories', ['contact_request_id' => $expired->id]);
        $this->assertModelExists($boundary);
        $this->assertModelExists($held);
        $this->assertStringNotContainsString('Expired Contact', $output);
        $this->assertDatabaseHas('retention_tombstones', [
            'scope' => 'contacts',
            'record_id' => $expired->id,
            'action' => 'contact-deleted',
        ]);
    }

    public function test_active_contacts_and_missing_terminal_timestamp_enter_exception_queue(): void
    {
        $stale = ContactRequest::factory()->create([
            'status' => 'processing',
            'updated_at' => CarbonImmutable::now()->subDays(366),
        ]);
        $missing = ContactRequest::factory()->create([
            'status' => 'completed',
            'completed_at' => null,
        ]);

        $this->assertSame(0, Artisan::call('retention:contacts', ['--apply' => true]));

        $this->assertDatabaseHas('retention_exceptions', [
            'record_id' => $stale->id,
            'reason_code' => 'active_contact_maximum_age_exceeded',
        ]);
        $this->assertDatabaseHas('retention_exceptions', [
            'record_id' => $missing->id,
            'reason_code' => 'terminal_timestamp_missing',
        ]);
        $this->assertModelExists($stale);
        $this->assertModelExists($missing);
    }

    public function test_technical_cleanup_is_bounded_and_preserves_live_rows(): void
    {
        config()->set('retention.batch_size', 1);
        $this->refreshPolicy();
        DB::table('failed_jobs')->insert([
            ['uuid' => 'old-1', 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'sanitised', 'failed_at' => now()->subDays(31)],
            ['uuid' => 'old-2', 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'sanitised', 'failed_at' => now()->subDays(31)],
            ['uuid' => 'live', 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'sanitised', 'failed_at' => now()->subDays(29)],
        ]);
        DB::table('sessions')->insert([
            ['id' => 'expired', 'payload' => '', 'last_activity' => now()->subHours(27)->getTimestamp()],
            ['id' => 'live', 'payload' => '', 'last_activity' => now()->subHours(25)->getTimestamp()],
        ]);
        DB::table('password_reset_tokens')->insert([
            ['email' => 'expired@example.test', 'token' => 'hash', 'created_at' => now()->subHours(26)],
            ['email' => 'live@example.test', 'token' => 'hash', 'created_at' => now()->subHours(24)],
        ]);

        $this->assertSame(0, Artisan::call('retention:technical', ['--apply' => true]));
        $output = Artisan::output();

        $this->assertSame(1, DB::table('failed_jobs')->whereIn('uuid', ['old-1', 'old-2'])->count());
        $this->assertDatabaseHas('failed_jobs', ['uuid' => 'live']);
        $this->assertDatabaseMissing('sessions', ['id' => 'expired']);
        $this->assertDatabaseHas('sessions', ['id' => 'live']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'expired@example.test']);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'live@example.test']);
        $this->assertStringNotContainsString('expired@example.test', $output);
    }

    public function test_invalid_zero_retention_value_is_rejected(): void
    {
        config()->set('retention.contacts.completed_days', 0);
        $this->refreshPolicy();

        $this->expectException(InvalidArgumentException::class);
        app(RetentionPolicy::class);
    }

    public function test_scheduler_registers_safe_dry_runs_while_apply_gate_is_closed(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(static fn ($event): string => $event->command ?? '')
            ->filter()
            ->values();

        $this->assertTrue($commands->contains(static fn (string $command): bool => str_contains($command, 'retention:orders') && ! str_contains($command, '--apply')));
        $this->assertTrue($commands->contains(static fn (string $command): bool => str_contains($command, 'retention:contacts') && ! str_contains($command, '--apply')));
        $this->assertTrue($commands->contains(static fn (string $command): bool => str_contains($command, 'retention:technical') && ! str_contains($command, '--apply')));
        $this->assertFalse($commands->contains(static fn (string $command): bool => str_contains($command, 'retention:') && str_contains($command, '--apply')));
    }

    public function test_synthetic_restore_replays_exported_tombstone_without_exposing_pii(): void
    {
        $original = [
            'customer_name' => 'Restore Person',
            'customer_phone' => '+79990001122',
            'customer_email' => 'restore@example.test',
            'delivery_address' => 'Synthetic address 10',
        ];
        $order = $this->expiredOrder($original);

        $this->assertSame(0, Artisan::call('retention:orders', ['--apply' => true]));
        $this->assertNull($order->fresh()?->customer_name);
        $this->assertSame(0, Artisan::call('retention:tombstones-export'));
        $journal = Artisan::output();
        $path = tempnam(sys_get_temp_dir(), 'retention-journal-');
        $this->assertNotFalse($path);
        file_put_contents($path, $journal);

        try {
            Order::query()->whereKey($order->id)->update(array_merge($original, [
                'order_number' => 'AC-RESTORED-SYNTHETIC',
                'anonymized_at' => null,
                'commercial_retention_until' => null,
            ]));
            OrderComment::factory()->for($order)->create(['body' => 'Restored private comment']);

            $exitCode = Artisan::call('retention:tombstones-replay', ['path' => $path, '--apply' => true]);
            $this->assertSame(0, $exitCode, Artisan::output());
            $output = Artisan::output();

            $this->assertNull($order->fresh()?->customer_name);
            $this->assertDatabaseMissing('order_comments', ['order_id' => $order->id]);
            $this->assertStringNotContainsString('Restore Person', $output);
            $this->assertStringNotContainsString('restore@example.test', $output);
            $this->assertDatabaseHas('retention_executions', [
                'scope' => 'restore',
                'action' => 'tombstone-replay',
                'status' => 'completed',
                'processed_count' => 1,
            ]);
        } finally {
            unlink($path);
        }
    }

    public function test_restore_replay_refuses_record_id_collision_with_different_subject(): void
    {
        $order = $this->expiredOrder([
            'customer_name' => 'Original Subject',
            'customer_email' => 'original@example.test',
        ]);
        $this->assertSame(0, Artisan::call('retention:orders', ['--apply' => true]));
        $this->assertSame(0, Artisan::call('retention:tombstones-export'));
        $path = tempnam(sys_get_temp_dir(), 'retention-journal-');
        $this->assertNotFalse($path);
        file_put_contents($path, Artisan::output());

        try {
            Order::query()->whereKey($order->id)->update([
                'order_number' => 'AC-RESTORED-COLLISION',
                'customer_name' => 'Different Subject',
                'customer_phone' => '+79995556677',
                'customer_email' => 'different@example.test',
                'delivery_address' => 'Different synthetic address',
                'anonymized_at' => null,
                'commercial_retention_until' => null,
            ]);

            $this->assertSame(1, Artisan::call('retention:tombstones-replay', ['path' => $path, '--apply' => true]));
            $output = Artisan::output();

            $this->assertSame('Different Subject', $order->fresh()?->customer_name);
            $this->assertStringContainsString('unexpected_failure', $output);
            $this->assertStringNotContainsString('Different Subject', $output);
            $this->assertStringNotContainsString('different@example.test', $output);
        } finally {
            unlink($path);
        }
    }

    public function test_apply_refuses_existing_tombstone_with_a_different_subject_fingerprint(): void
    {
        $order = $this->expiredOrder(['customer_name' => 'Protected Subject']);
        DB::table('retention_tombstones')->insert([
            'id' => (string) Str::uuid(),
            'batch_id' => (string) Str::uuid(),
            'scope' => 'orders',
            'record_id' => $order->id,
            'action' => 'order-anonymized',
            'key_id' => 'v1',
            'subject_hmac' => str_repeat('0', 64),
            'occurred_at' => now()->subDay(),
        ]);

        $this->assertSame(1, Artisan::call('retention:orders', ['--apply' => true]));

        $this->assertSame('Protected Subject', $order->fresh()?->customer_name);
        $this->assertStringContainsString('unexpected_failure', Artisan::output());
        $this->assertStringNotContainsString('Protected Subject', Artisan::output());
    }

    /** @param array<string, mixed> $attributes */
    private function expiredOrder(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'status' => 'completed',
            'completed_at' => CarbonImmutable::now()->subYears(4),
        ], $attributes));
    }

    private function configureAcceptedPolicy(): void
    {
        config()->set('retention.policy_status', 'accepted');
        config()->set('retention.apply_enabled', true);
        config()->set('retention.tombstone_key', 'synthetic-test-only-key');
        config()->set('retention.orders.commercial_disposition', 'retain_commercial');
        config()->set('retention.batch_size', 100);
        $this->refreshPolicy();
    }

    private function refreshPolicy(): void
    {
        app()->forgetInstance(RetentionPolicy::class);
    }
}
