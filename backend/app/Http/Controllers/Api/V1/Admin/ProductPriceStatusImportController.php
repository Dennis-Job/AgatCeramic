<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreProductPriceStatusImportRequest;
use App\Http\Resources\Catalog\ProductImportResource;
use App\Jobs\ProcessProductImport;
use App\Models\Product;
use App\Models\ProductImport;
use App\Services\ProductExportService;
use App\Services\ProductPriceStatusImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ProductPriceStatusImportController extends Controller
{
    public function template(ProductPriceStatusImportService $service): BinaryFileResponse
    {
        Gate::authorize('import', Product::class);
        $file = $service->createTemplate();

        return response()->download($file['path'], $file['name'], ['Content-Type' => ProductExportService::CONTENT_TYPE])->deleteFileAfterSend();
    }

    public function store(StoreProductPriceStatusImportRequest $request): JsonResponse
    {
        Gate::authorize('import', Product::class);
        $file = $request->file('file');
        $path = $file->store('product-price-status-imports', 'local');
        if ($path === false) {
            throw new RuntimeException('Не удалось сохранить XLSX-файл для импорта.');
        }
        try {
            $import = ProductImport::query()->create([
                'user_id' => $request->user()->id, 'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                'disk' => 'local', 'path' => $path, 'status' => 'pending', 'operation' => 'price_status',
            ]);
            ProcessProductImport::dispatch($import->id);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return (new ProductImportResource($import))->response()->setStatusCode(202);
    }

    public function show(Request $request, ProductImport $productImport): ProductImportResource
    {
        Gate::authorize('import', Product::class);
        abort_unless($productImport->operation === 'price_status' && $productImport->user_id === $request->user()->id, 404);

        return new ProductImportResource($productImport);
    }

    public function errors(Request $request, ProductImport $productImport, ProductPriceStatusImportService $service): BinaryFileResponse
    {
        Gate::authorize('import', Product::class);
        abort_unless($productImport->operation === 'price_status' && $productImport->user_id === $request->user()->id && $productImport->failed_rows > 0 && in_array($productImport->status, ['completed', 'failed'], true), 404);
        $file = $service->createErrorReport($productImport);

        return response()->download($file['path'], $file['name'], ['Content-Type' => ProductExportService::CONTENT_TYPE])->deleteFileAfterSend();
    }
}
