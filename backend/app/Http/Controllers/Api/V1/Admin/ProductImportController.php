<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ImportProductsRequest;
use App\Http\Resources\Catalog\ProductImportResource;
use App\Jobs\ProcessProductImport;
use App\Models\Product;
use App\Models\ProductImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProductImportController extends Controller
{
    public function store(ImportProductsRequest $request): JsonResponse
    {
        Gate::authorize('import', Product::class);

        $file = $request->file('file');
        $path = $file->store('product-imports', 'local');
        if ($path === false) {
            throw new RuntimeException('Не удалось сохранить XLSX-файл для импорта.');
        }

        try {
            $import = ProductImport::query()->create([
                'user_id' => $request->user()->id,
                'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                'disk' => 'local',
                'path' => $path,
                'status' => 'pending',
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
        abort_unless($productImport->user_id === $request->user()->id, 404);

        return new ProductImportResource($productImport);
    }
}
