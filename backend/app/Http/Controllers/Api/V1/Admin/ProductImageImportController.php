<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreProductImageImportRequest;
use App\Http\Resources\Catalog\ProductImageImportResource;
use App\Jobs\ProcessProductImageImport;
use App\Models\Product;
use App\Models\ProductImageImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProductImageImportController extends Controller
{
    public function store(StoreProductImageImportRequest $request): JsonResponse
    {
        Gate::authorize('import', Product::class);
        $file = $request->file('file');
        $path = $file->store('product-image-imports', 'local');
        if ($path === false) {
            throw new RuntimeException('Не удалось сохранить ZIP-архив.');
        }
        try {
            $import = ProductImageImport::query()->create(['user_id' => $request->user()->id, 'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'disk' => 'local', 'path' => $path, 'status' => 'pending']);
            ProcessProductImageImport::dispatch($import->id);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return (new ProductImageImportResource($import->load('errors')))->response()->setStatusCode(202);
    }

    public function show(Request $request, ProductImageImport $productImageImport): ProductImageImportResource
    {
        Gate::authorize('import', Product::class);
        abort_unless($productImageImport->user_id === $request->user()->id, 404);

        return new ProductImageImportResource($productImageImport->load('errors'));
    }

    public function errors(Request $request, ProductImageImport $productImageImport): StreamedResponse
    {
        Gate::authorize('import', Product::class);
        abort_unless($productImageImport->user_id === $request->user()->id && $productImageImport->failed_folders > 0, 404);

        return response()->streamDownload(function () use ($productImageImport): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['SKU', 'Файл', 'Ошибки']);
            foreach ($productImageImport->errors as $error) {
                fputcsv($out, [$error->sku, $error->entry, implode('; ', $error->messages ?? [])]);
            }
            fclose($out);
        }, "product-image-import-{$productImageImport->id}-errors.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
