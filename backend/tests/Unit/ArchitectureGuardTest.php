<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgatCeramic\Architecture\ArchitectureGuard;
use AgatCeramic\Architecture\ArchitectureReport;
use AgatCeramic\Architecture\ArchitectureViolation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../scripts/architecture/ArchitectureGuard.php';

final class ArchitectureGuardTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    #[DataProvider('forbiddenDependencies')]
    public function test_it_rejects_reverse_layer_dependencies(string $path, string $source, string $rule): void
    {
        $report = $this->inspectFixture($path, $source);

        self::assertContains($rule, array_map(
            static fn (ArchitectureViolation $violation): string => $violation->rule,
            $report->violations,
        ));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function forbiddenDependencies(): iterable
    {
        yield 'application to HTTP' => [
            'Services/BadService.php',
            <<<'PHP'
                <?php
                namespace App\Services;
                use App\Http\Requests\BadRequest;
                final class BadService { public function run(BadRequest $request): void {} }
                PHP,
            'dependency.application',
        ];
        yield 'application to framework response' => [
            'Services/BadService.php',
            <<<'PHP'
                <?php
                namespace App\Services;
                use Illuminate\Http\Response;
                final class BadService { public function run(): Response { return new Response; } }
                PHP,
            'dependency.application',
        ];
        yield 'model to application' => [
            'Models/BadModel.php',
            <<<'PHP'
                <?php
                namespace App\Models;
                use App\Services\BadService;
                final class BadModel { public function run(BadService $service): void {} }
                PHP,
            'dependency.model',
        ];
        yield 'query to application' => [
            'Queries/BadQuery.php',
            <<<'PHP'
                <?php
                namespace App\Queries;
                use App\Services\BadService;
                final class BadQuery { public function __construct(BadService $service) {} }
                PHP,
            'dependency.query',
        ];
        yield 'presentation to application' => [
            'Http/Resources/BadResource.php',
            <<<'PHP'
                <?php
                namespace App\Http\Resources;
                use App\Services\BadService;
                final class BadResource { public function __construct(BadService $service) {} }
                PHP,
            'dependency.presentation',
        ];
        yield 'integration to presentation' => [
            'Jobs/BadJob.php',
            <<<'PHP'
                <?php
                namespace App\Jobs;
                use App\Http\Resources\BadResource;
                final class BadJob { public function handle(BadResource $resource): void {} }
                PHP,
            'dependency.integration',
        ];
    }

    #[DataProvider('forbiddenControllerOperations')]
    public function test_it_rejects_controller_service_locators_and_persistence(
        string $source,
        string $rule,
    ): void {
        $report = $this->inspectFixture('Http/Controllers/BadController.php', $source);

        self::assertContains($rule, array_map(
            static fn (ArchitectureViolation $violation): string => $violation->rule,
            $report->violations,
        ));
    }

    /** @return iterable<string, array{string, string}> */
    public static function forbiddenControllerOperations(): iterable
    {
        yield 'DB facade' => [
            <<<'PHP'
                <?php
                namespace App\Http\Controllers;
                use Illuminate\Support\Facades\DB;
                final class BadController { public function store(): void { DB::transaction(fn () => null); } }
                PHP,
            'controller.db_facade',
        ];
        yield 'service locator' => [
            <<<'PHP'
                <?php
                namespace App\Http\Controllers;
                final class BadController { public function store(): void { app('service'); } }
                PHP,
            'controller.service_locator',
        ];
        yield 'global request' => [
            <<<'PHP'
                <?php
                namespace App\Http\Controllers;
                final class BadController { public function store(): void { request(); } }
                PHP,
            'controller.global_request',
        ];
        yield 'global auth' => [
            <<<'PHP'
                <?php
                namespace App\Http\Controllers;
                final class BadController { public function store(): void { auth(); } }
                PHP,
            'controller.global_auth',
        ];
        yield 'static model mutation' => [
            <<<'PHP'
                <?php
                namespace App\Http\Controllers;
                use App\Models\Product;
                final class BadController { public function store(): void { Product::create([]); } }
                PHP,
            'controller.model_mutation',
        ];
        yield 'bound model mutation' => [
            <<<'PHP'
                <?php
                namespace App\Http\Controllers;
                use App\Models\Product;
                final class BadController { public function update(Product $product): void { $product->update([]); } }
                PHP,
            'controller.model_mutation',
        ];
        yield 'fluent model query mutation' => [
            <<<'PHP'
                <?php
                namespace App\Http\Controllers;
                use App\Models\Product;
                final class BadController { public function update(): void { Product::query()->update([]); } }
                PHP,
            'controller.model_mutation',
        ];
        yield 'assigned model mutation' => [
            <<<'PHP'
                <?php
                namespace App\Http\Controllers;
                use App\Models\Product;
                final class BadController
                {
                    public function update(): void
                    {
                        $product = Product::query()->firstOrFail();
                        $product->save();
                    }
                }
                PHP,
            'controller.model_mutation',
        ];
    }

    public function test_allowlist_requires_a_removal_task_and_fails_when_stale(): void
    {
        $root = $this->fixtureDirectory();
        $this->writeFixture($root, 'Services/BadService.php', <<<'PHP'
            <?php
            namespace App\Services;
            use App\Http\Requests\BadRequest;
            final class BadService { public function run(BadRequest $request): void {} }
            PHP);
        $violation = (new ArchitectureGuard($root))->inspect()->violations[0];
        $entry = [
            'path' => $violation->path,
            'rule' => $violation->rule,
            'line' => $violation->line,
            'task' => 'TASK-A999',
            'reason' => 'Temporary migration debt.',
        ];

        $allowlisted = (new ArchitectureGuard($root, [$entry]))->inspect();

        self::assertSame([], $allowlisted->violations);
        self::assertCount(1, $allowlisted->allowlistedViolations);

        $entry['task'] = 'later';
        $invalid = (new ArchitectureGuard($root, [$entry]))->inspect();
        self::assertContains('allowlist.invalid', array_column($invalid->violations, 'rule'));

        $entry['task'] = 'TASK-A999';
        $entry['line']++;
        $stale = (new ArchitectureGuard($root, [$entry]))->inspect();
        self::assertContains('allowlist.stale', array_column($stale->violations, 'rule'));
    }

    public function test_it_reports_size_and_complexity_review_thresholds_without_blocking(): void
    {
        $body = implode("\n", array_fill(0, 11, 'if ($value) { $value = false; }'));
        $padding = str_repeat("\n", ArchitectureGuard::METHOD_LINE_REVIEW_THRESHOLD + 1);
        $source = "<?php\nnamespace App\\Services;\nfinal class LargeService\n{\n"
            ."public function run(bool \$value): void\n{\n{$body}{$padding}\n}\n}\n";

        $report = $this->inspectFixture('Services/LargeService.php', $source);

        self::assertSame([], $report->violations);
        self::assertNotEmpty($report->reviewItems);
        self::assertStringContainsString('method exceeds', $report->reviewItems[0]->reason);
        self::assertStringContainsString('cyclomatic complexity exceeds', $report->reviewItems[0]->reason);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $this->deleteDirectory($directory);
        }

        parent::tearDown();
    }

    private function inspectFixture(string $path, string $source): ArchitectureReport
    {
        $root = $this->fixtureDirectory();
        $this->writeFixture($root, $path, $source);

        return (new ArchitectureGuard($root))->inspect();
    }

    private function fixtureDirectory(): string
    {
        $directory = sys_get_temp_dir().'/agat-architecture-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0777, true));
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }

    private function writeFixture(string $root, string $path, string $source): void
    {
        $target = $root.'/'.$path;
        $directory = dirname($target);

        if (! is_dir($directory)) {
            self::assertTrue(mkdir($directory, 0777, true));
        }

        self::assertNotFalse(file_put_contents($target, $source));
    }

    private function deleteDirectory(string $directory): void
    {
        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.'/'.$item;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
