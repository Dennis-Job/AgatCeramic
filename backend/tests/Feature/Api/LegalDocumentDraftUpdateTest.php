<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\LegalDocumentVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\LegalDocumentManagementService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LegalDocumentDraftUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_update_preserves_identity_author_and_other_versions_and_publishes_corrected_content(): void
    {
        $admin = $this->admin();
        $draft = $this->draft($admin);
        $other = $this->draft($admin, 'other');
        $content = $this->content('Исправленный текст');

        $this->actingAs($admin)->patchJson($this->url($draft), [
            'type' => 'offer', 'version' => 'v1', 'body' => 'Ignored', 'content' => $content,
            'created_by' => 999, 'published_at' => now()->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.id', $draft->id)
            ->assertJsonPath('data.body', 'Исправленный текст')->assertJsonPath('data.content', $content)
            ->assertJsonPath('data.published_at', null);

        $this->assertDatabaseCount('legal_document_versions', 2);
        $this->assertSame($admin->id, $draft->fresh()->created_by);
        $this->assertSame('Старый текст', $other->fresh()->body);
        $this->getJson('/api/v1/legal-documents/offer')->assertNotFound();
        $log = AuditLog::query()->where('action', 'legal-document.updated')->sole();
        $this->assertSame((string) $draft->id, (string) $log->entity_id);
        $this->assertSame(['type' => 'offer', 'version' => 'v1'], $log->metadata);
        $this->postJson($this->url($draft).'/publish')->assertOk();
        $this->getJson('/api/v1/legal-documents/offer')->assertOk()
            ->assertJsonPath('data.body', 'Исправленный текст')->assertJsonPath('data.content', $content);
    }

    public function test_plain_update_clears_old_rich_content_with_omitted_or_null_content(): void
    {
        $admin = $this->admin();
        foreach ([[], ['content' => null]] as $index => $content) {
            $draft = $this->draft($admin, 'plain-'.$index);
            $draft->update(['content' => $this->content('Старый текст')]);
            $this->actingAs($admin)->patchJson($this->url($draft), [
                'version' => 'renamed-'.$index, 'body' => "Простой текст\nНовая строка", ...$content,
            ])->assertOk()->assertJsonPath('data.version', 'renamed-'.$index)
                ->assertJsonPath('data.body', "Простой текст\nНовая строка")->assertJsonPath('data.content', null);
        }
    }

    public function test_invalid_updates_leave_the_draft_untouched(): void
    {
        $admin = $this->admin();
        $draft = $this->draft($admin);
        $this->draft($admin, 'taken');
        $valid = ['version' => 'v1', 'body' => 'Исправленный текст'];
        foreach ([
            ['version' => 'taken'], ['version' => ''], ['version' => 'bad version'], ['version' => str_repeat('x', 65)],
            ['body' => '  '], ['body' => str_repeat('x', 200001)], ['type' => 'consent'],
            ['content' => ['type' => 'doc', 'content' => [['type' => 'html', 'text' => '<script>alert(1)</script>']]]],
        ] as $invalid) {
            $this->actingAs($admin)->patchJson($this->url($draft), [...$valid, ...$invalid])->assertUnprocessable();
            $this->assertSame('Старый текст', $draft->fresh()->body);
            $this->assertSame('v1', $draft->fresh()->version);
        }
        $this->assertDatabaseMissing('audit_logs', ['action' => 'legal-document.updated']);
        $this->patchJson('/api/v1/admin/legal-documents/999999', $valid)->assertNotFound();
    }

    public function test_update_requires_settings_manage_permission(): void
    {
        $admin = $this->admin();
        $draft = $this->draft($admin);
        $payload = ['version' => 'v1', 'body' => 'Исправление'];
        $this->patchJson($this->url($draft), $payload)->assertUnauthorized();
        $analyst = User::factory()->create();
        $analyst->roles()->attach(Role::query()->where('slug', 'analyst')->sole());
        $this->actingAs($analyst)->patchJson($this->url($draft), $payload)->assertForbidden();
        $this->assertSame('Старый текст', $draft->fresh()->body);
    }

    public function test_published_versions_cannot_be_changed_even_through_a_stale_draft_model(): void
    {
        $admin = $this->admin();
        $staleDraft = $this->draft($admin);
        $management = $this->app->make(LegalDocumentManagementService::class);
        $management->publish($admin, $staleDraft);
        $payload = ['version' => 'v1', 'body' => 'Исправление'];
        $this->actingAs($admin)->patchJson($this->url($staleDraft), $payload)->assertUnprocessable();
        try {
            $management->update($admin, $staleDraft, $payload);
            $this->fail('Published document update must be rejected after reloading the stale draft.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }
        $this->assertSame('Старый текст', $staleDraft->fresh()->body);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'legal-document.updated']);
    }

    private function admin(): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'administrator')->sole());

        return $user;
    }

    private function draft(User $author, string $version = 'v1'): LegalDocumentVersion
    {
        return LegalDocumentVersion::query()->create([
            'type' => 'offer', 'version' => $version, 'body' => 'Старый текст', 'created_by' => $author->id,
        ]);
    }

    private function url(LegalDocumentVersion $document): string
    {
        return '/api/v1/admin/legal-documents/'.$document->id;
    }

    /** @return array<string, mixed> */
    private function content(string $text): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'attrs' => ['textAlign' => 'justify'], 'content' => [
            ['type' => 'text', 'text' => $text, 'marks' => [['type' => 'bold']]],
        ]]]];
    }
}
