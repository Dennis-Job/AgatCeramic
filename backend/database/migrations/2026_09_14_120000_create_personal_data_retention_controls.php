<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_name')->nullable()->change();
            $table->string('customer_phone', 30)->nullable()->change();
            $table->string('delivery_address', 1000)->nullable()->change();
            $table->timestamp('anonymized_at')->nullable()->index();
            $table->timestamp('commercial_retention_until')->nullable()->index();
        });

        Schema::create('retention_legal_holds', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 32);
            $table->unsignedBigInteger('record_id');
            $table->string('case_reference', 100);
            $table->string('legal_basis', 500);
            $table->string('owner_reference', 100);
            $table->timestamp('started_at');
            $table->timestamp('review_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['scope', 'record_id', 'released_at'], 'retention_holds_lookup_index');
            $table->index(['released_at', 'review_at'], 'retention_holds_review_index');
        });

        Schema::create('retention_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 32);
            $table->unsignedBigInteger('record_id');
            $table->string('reason_code', 64);
            $table->timestamp('first_detected_at');
            $table->timestamp('last_detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['scope', 'record_id', 'reason_code'], 'retention_exceptions_identity_unique');
            $table->index(['scope', 'resolved_at', 'last_detected_at'], 'retention_exceptions_queue_index');
        });

        Schema::create('retention_executions', function (Blueprint $table): void {
            $table->uuid('batch_id')->primary();
            $table->string('policy_version', 32);
            $table->string('scope', 32);
            $table->string('action', 64);
            $table->string('mode', 16);
            $table->string('status', 16);
            $table->timestamp('cutoff_at')->nullable();
            $table->unsignedInteger('eligible_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('hold_count')->default(0);
            $table->unsignedInteger('exception_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->string('failure_code', 64)->nullable();
            $table->string('service_identity', 100);
            $table->timestamp('occurred_at');
            $table->index(['scope', 'occurred_at']);
            $table->index(['status', 'occurred_at']);
        });

        Schema::create('retention_tombstones', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('batch_id');
            $table->string('scope', 32);
            $table->unsignedBigInteger('record_id');
            $table->string('action', 64);
            $table->string('key_id', 64)->nullable();
            $table->string('subject_hmac', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->unique(['scope', 'record_id', 'action'], 'retention_tombstones_identity_unique');
            $table->index(['batch_id', 'occurred_at']);
            $table->index(['subject_hmac', 'scope']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX retention_holds_one_active_per_record ON retention_legal_holds (scope, record_id) WHERE released_at IS NULL');
        }

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE retention_legal_holds
                ADD CONSTRAINT retention_holds_scope_check CHECK (scope IN ('orders', 'contacts')),
                ADD CONSTRAINT retention_holds_review_check CHECK (
                    review_at > started_at
                    AND review_at <= started_at + INTERVAL '90 days'
                    AND (released_at IS NULL OR released_at >= started_at)
                );

                CREATE OR REPLACE FUNCTION prevent_retention_evidence_mutation()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    RAISE EXCEPTION 'retention evidence is immutable';
                END;
                $$;

                CREATE TRIGGER retention_executions_immutable
                BEFORE UPDATE OR DELETE ON retention_executions
                FOR EACH ROW EXECUTE FUNCTION prevent_retention_evidence_mutation();

                CREATE TRIGGER retention_tombstones_immutable
                BEFORE UPDATE OR DELETE ON retention_tombstones
                FOR EACH ROW EXECUTE FUNCTION prevent_retention_evidence_mutation();
            SQL);
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS retention_tombstones_immutable ON retention_tombstones;
                DROP TRIGGER IF EXISTS retention_executions_immutable ON retention_executions;
                DROP FUNCTION IF EXISTS prevent_retention_evidence_mutation();
            SQL);
        }

        Schema::dropIfExists('retention_tombstones');
        Schema::dropIfExists('retention_executions');
        Schema::dropIfExists('retention_exceptions');
        Schema::dropIfExists('retention_legal_holds');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['anonymized_at']);
            $table->dropIndex(['commercial_retention_until']);
            $table->dropColumn(['anonymized_at', 'commercial_retention_until']);
            // This intentionally fails if irreversible apply already produced null PII.
            $table->string('customer_name')->nullable(false)->change();
            $table->string('customer_phone', 30)->nullable(false)->change();
            $table->string('delivery_address', 1000)->nullable(false)->change();
        });
    }
};
