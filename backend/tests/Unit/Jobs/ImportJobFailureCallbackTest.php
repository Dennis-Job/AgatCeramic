<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessProductImageImport;
use App\Jobs\ProcessProductImport;
use App\Services\ImportLifecycleService;
use RuntimeException;
use Tests\TestCase;

class ImportJobFailureCallbackTest extends TestCase
{
    public function test_product_import_failure_callback_delegates_to_the_container_service(): void
    {
        $lifecycle = $this->createMock(ImportLifecycleService::class);
        $lifecycle->expects($this->once())
            ->method('failProductImport')
            ->with(42, 'Не удалось обработать XLSX-файл.');
        $this->app->instance(ImportLifecycleService::class, $lifecycle);

        (new ProcessProductImport(42))->failed(new RuntimeException('worker failure'));
    }

    public function test_product_image_import_failure_callback_delegates_to_the_container_service(): void
    {
        $lifecycle = $this->createMock(ImportLifecycleService::class);
        $lifecycle->expects($this->once())
            ->method('failProductImageImport')
            ->with(84, 'disk unavailable');
        $this->app->instance(ImportLifecycleService::class, $lifecycle);

        (new ProcessProductImageImport(84))->failed(new RuntimeException('disk unavailable'));
    }
}
