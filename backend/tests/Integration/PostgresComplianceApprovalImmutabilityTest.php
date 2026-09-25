<?php

namespace Tests\Integration;

use App\Models\ComplianceApproval;
use App\Models\LegalDocumentVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresComplianceApprovalImmutabilityTest extends TestCase
{
    public function test_postgresql_rejects_direct_approval_update(): void
    {
        $approval = $this->approval();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('compliance approvals are append-only');

        ComplianceApproval::query()->whereKey($approval->id)->update(['decision' => 'rejected']);
    }

    public function test_postgresql_rejects_direct_approval_delete(): void
    {
        $approval = $this->approval();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('compliance approvals are append-only');

        ComplianceApproval::query()->whereKey($approval->id)->delete();
    }

    private function approval(): ComplianceApproval
    {
        if (getenv('CI') !== 'true' || DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This integration test requires CI PostgreSQL.');
        }

        $actor = User::factory()->create();
        $document = LegalDocumentVersion::query()->create([
            'type' => 'privacy_policy',
            'version' => uniqid('test-', true),
            'body' => 'Synthetic text',
            'published_at' => now(),
            'created_by' => $actor->id,
        ]);

        return ComplianceApproval::query()->create([
            'role' => 'legal_reviewer',
            'reviewer_name' => 'Synthetic reviewer',
            'decision' => 'approved',
            'decided_on' => now()->toDateString(),
            'document_version_id' => $document->id,
            'recorded_by' => $actor->id,
        ]);
    }
}
