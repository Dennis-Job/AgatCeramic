<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('operator_type', 32)->nullable();
            $table->string('seller_name')->nullable();
            $table->string('entrepreneur_name')->nullable();
            $table->string('inn', 12)->nullable();
            $table->string('ogrnip', 15)->nullable();
            $table->text('address')->nullable();
            $table->json('phones')->default('[]');
            $table->string('email')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_bik', 9)->nullable();
            $table->string('bank_account', 20)->nullable();
            $table->string('bank_correspondent_account', 20)->nullable();
            $table->boolean('publish_bank_details')->default(false);
            $table->timestamps();
        });
        DB::table('site_settings')->insert(['id' => 1, 'phones' => '[]', 'publish_bank_details' => false, 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('legal_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->string('version', 64);
            $table->longText('body');
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['type', 'version']);
            $table->index(['type', 'published_at']);
        });

        Schema::create('compliance_approvals', function (Blueprint $table): void {
            $table->id();
            $table->string('role', 32);
            $table->string('reviewer_name');
            $table->string('decision', 16);
            $table->date('decided_on');
            $table->foreignId('document_version_id')->constrained('legal_document_versions')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['role', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION prevent_compliance_approval_mutation() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'compliance approvals are append-only'; END; $$ LANGUAGE plpgsql");
            DB::unprepared('CREATE TRIGGER compliance_approvals_immutable BEFORE UPDATE OR DELETE ON compliance_approvals FOR EACH ROW EXECUTE FUNCTION prevent_compliance_approval_mutation()');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS compliance_approvals_immutable ON compliance_approvals');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_compliance_approval_mutation()');
        }
        Schema::dropIfExists('compliance_approvals');
        Schema::dropIfExists('legal_document_versions');
        Schema::dropIfExists('site_settings');
    }
};
