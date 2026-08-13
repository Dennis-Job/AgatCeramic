<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_actor_id_foreign;

            CREATE OR REPLACE FUNCTION prevent_audit_log_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND current_setting('app.audit_log_prune', true) = 'on' THEN
                    RETURN OLD;
                END IF;

                RAISE EXCEPTION 'audit_logs are immutable';
            END;
            $$;

            CREATE TRIGGER audit_logs_immutable
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW
            EXECUTE FUNCTION prevent_audit_log_mutation();
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS audit_logs_immutable ON audit_logs;
            DROP FUNCTION IF EXISTS prevent_audit_log_mutation();
        SQL);
    }
};
