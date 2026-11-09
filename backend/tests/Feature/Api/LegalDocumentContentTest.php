<?php

namespace Tests\Feature\Api;

use App\Models\LegalDocumentVersion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegalDocumentContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_rich_content_round_trips_and_body_is_derived_before_publication(): void
    {
        $admin = $this->admin();
        $content = ['type' => 'doc', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2, 'textAlign' => 'right'], 'content' => [['type' => 'text', 'text' => 'Условия']]],
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'justify'], 'content' => [
                ['type' => 'text', 'text' => 'Важный', 'marks' => [['type' => 'bold'], ['type' => 'italic']]],
                ['type' => 'hardBreak'],
                ['type' => 'text', 'text' => ' текст', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/contacts']]]],
            ]],
            ['type' => 'orderedList', 'attrs' => ['start' => 3], 'content' => [
                ['type' => 'listItem', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Пункт']]],
                    ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [
                        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Подпункт']]],
                    ]]]],
                ]],
            ]],
        ]];
        $created = $this->actingAs($admin)->postJson('/api/v1/admin/legal-documents', [
            'type' => 'offer', 'version' => 'rich-v1', 'body' => 'Несовпадающий текст', 'content' => $content,
        ])->assertCreated()->assertJsonPath('data.content', $content)
            ->assertJsonPath('data.body', "Условия\n\nВажный\n текст\n\nПункт\nПодпункт")
            ->assertJsonPath('data.published_at', null);
        $this->getJson('/api/v1/legal-documents/offer')->assertNotFound();
        $id = $created->json('data.id');
        $this->actingAs($admin)->postJson("/api/v1/admin/legal-documents/{$id}/publish")->assertOk();
        $this->getJson('/api/v1/legal-documents/offer')->assertOk()->assertJsonPath('data.content', $content)
            ->assertJsonPath('data.body', "Условия\n\nВажный\n текст\n\nПункт\nПодпункт");
        $this->actingAs($admin)->getJson('/api/v1/admin/legal-documents')->assertOk()->assertJsonPath('data.0.content', $content);
        $this->assertSame($content, LegalDocumentVersion::query()->findOrFail($id)->content);
    }

    public function test_plain_documents_remain_compatible_and_migration_preserves_them(): void
    {
        $admin = $this->admin();
        foreach ([[], ['content' => null]] as $index => $attributes) {
            $this->actingAs($admin)->postJson('/api/v1/admin/legal-documents', [
                'type' => 'consent', 'version' => 'plain-'.$index, 'body' => "Простой текст\nВторая строка", ...$attributes,
            ])->assertCreated()->assertJsonPath('data.content', null)->assertJsonPath('data.body', "Простой текст\nВторая строка");
        }
        $migration = require database_path('migrations/2026_11_09_000001_add_content_to_legal_document_versions.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('legal_document_versions', 'content'));
        $migration->up();
        $this->assertDatabaseHas('legal_document_versions', ['version' => 'plain-0', 'body' => "Простой текст\nВторая строка", 'content' => null]);
    }

    public function test_unsafe_or_malformed_content_is_rejected_without_creating_versions(): void
    {
        $this->actingAs($this->admin());
        $paragraph = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Текст']]];
        $invalid = [
            ['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['src' => 'https://example.test/a.png']]]],
            ['type' => 'doc', 'attrs' => ['onclick' => 'alert(1)'], 'content' => [$paragraph]],
            ['type' => 'doc', 'content' => [['type' => 'text', 'text' => 'Текст']]],
            ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [$paragraph]]]],
            ['type' => 'doc', 'content' => [['type' => 'heading', 'attrs' => ['level' => 1], 'content' => $paragraph['content']]]],
            ['type' => 'doc', 'content' => [['type' => 'paragraph', 'attrs' => ['textAlign' => 'center'], 'content' => $paragraph['content']]]],
            ['type' => 'doc', 'content' => [['type' => 'paragraph', 'attrs' => ['style' => 'color:red'], 'content' => $paragraph['content']]]],
            ['type' => 'doc', 'content' => [['type' => 'paragraph', 'attrs' => null, 'content' => $paragraph['content']]]],
            ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => null], $paragraph]],
            ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Текст', 'marks' => null]]]]],
            ['type' => 'doc', 'content' => [['type' => 'bulletList', 'content' => [$paragraph]]]],
            ['type' => 'doc', 'content' => [['type' => 'orderedList', 'attrs' => ['start' => 0], 'content' => [['type' => 'listItem', 'content' => [$paragraph]]]]]],
            ['type' => 'doc', 'content' => [['type' => 'listItem', 'content' => [$paragraph]]]],
            ['type' => 'doc', 'content' => []],
            ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => " \n\u{00A0}"]]]]],
        ];
        foreach (['underline', 'script'] as $mark) {
            $invalid[] = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Текст', 'marks' => [['type' => $mark]]]]]]];
        }
        foreach (['javascript:alert(1)', 'data:text/html,test', '//evil.test', '/\\evil.test', "https://example.test/\nattack", 'https:example.test', 'file:///etc/passwd'] as $href) {
            $invalid[] = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Текст', 'marks' => [['type' => 'link', 'attrs' => ['href' => $href]]]]]]]];
        }
        $invalid[] = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Текст', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.test', 'onclick' => 'alert(1)']]]]]]]];
        foreach ($invalid as $index => $content) {
            $this->postJson('/api/v1/admin/legal-documents', ['type' => 'offer', 'version' => 'invalid-'.$index, 'body' => 'Fallback', 'content' => $content])
                ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['content']]]);
        }
        $this->assertDatabaseCount('legal_document_versions', 0);
    }

    public function test_safe_links_and_headings_are_accepted_but_content_limits_are_enforced(): void
    {
        $this->actingAs($this->admin());
        foreach (['https://example.test/a?b=1', 'http://example.test', 'mailto:contact@example.test', '/privacy-policy'] as $index => $href) {
            $this->postJson('/api/v1/admin/legal-documents', ['type' => 'offer', 'version' => 'link-'.$index, 'body' => 'Текст', 'content' => [
                'type' => 'doc', 'content' => [['type' => 'heading', 'attrs' => ['level' => 3, 'textAlign' => 'left'], 'content' => [
                    ['type' => 'text', 'text' => 'Текст', 'marks' => [['type' => 'link', 'attrs' => ['href' => $href]]]],
                ]]],
            ]])->assertCreated();
        }
        $paragraph = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Текст']]];
        $nested = ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [$paragraph]]]];
        for ($depth = 0; $depth < 20; $depth++) {
            $nested = ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [$paragraph, $nested]]]];
        }
        foreach ([
            ['type' => 'doc', 'content' => [$nested]],
            ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => array_fill(0, 5000, ['type' => 'text', 'text' => 'x'])]]],
            ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => str_repeat('x', 200000)]]]]],
        ] as $index => $content) {
            $this->postJson('/api/v1/admin/legal-documents', ['type' => 'offer', 'version' => 'limit-'.$index, 'body' => 'Текст', 'content' => $content])
                ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['content']]]);
        }
    }

    public function test_nested_lists_within_the_document_depth_limit_are_accepted(): void
    {
        $this->actingAs($this->admin());
        $paragraph = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Пункт']]];
        $nested = ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [$paragraph]]]];
        for ($depth = 0; $depth < 6; $depth++) {
            $nested = ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [$paragraph, $nested]]]];
        }
        $this->postJson('/api/v1/admin/legal-documents', [
            'type' => 'offer', 'version' => 'nested', 'body' => 'Пункт', 'content' => ['type' => 'doc', 'content' => [$nested]],
        ])->assertCreated();
    }

    public function test_rich_content_does_not_bypass_settings_permissions(): void
    {
        $payload = ['type' => 'offer', 'version' => 'restricted', 'body' => 'Текст', 'content' => [
            'type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Текст']]]],
        ]];
        $this->postJson('/api/v1/admin/legal-documents', $payload)->assertUnauthorized();
        $this->admin();
        $analyst = User::factory()->create();
        $analyst->roles()->attach(Role::query()->where('slug', 'analyst')->sole());
        $this->actingAs($analyst)->postJson('/api/v1/admin/legal-documents', $payload)->assertForbidden();
        $this->assertDatabaseCount('legal_document_versions', 0);
    }

    private function admin(): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'administrator')->sole());

        return $user;
    }
}
