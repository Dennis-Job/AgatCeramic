<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ImportProductsRequest;
use App\Http\Requests\Api\V1\Admin\ProductImportTemplateRequest;
use App\Http\Resources\Catalog\ProductImportResource;
use App\Jobs\ProcessProductImport;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Services\ProductExportService;
use App\Services\ProductImportTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ProductImportController extends Controller
{
    public function template(ProductImportTemplateRequest $request, ProductImportTemplateService $service): BinaryFileResponse
    {
        Gate::authorize('import', Product::class);
        $file = $service->create(Category::query()->findOrFail($request->integer('category_id')));

        return response()->download($file['path'], $file['name'], ['Content-Type' => ProductExportService::CONTENT_TYPE])->deleteFileAfterSend();
    }

    public function errors(Request $request, ProductImport $productImport, ProductImportTemplateService $service): BinaryFileResponse
    {
        Gate::authorize('import', Product::class);
        abort_unless($productImport->user_id === $request->user()->id, 404);
        abort_unless(in_array($productImport->status, ['completed', 'failed'], true) && $productImport->failed_rows > 0 && $productImport->category_id !== null, 404);
        $category = Category::query()->findOrFail($productImport->category_id);
        $file = $service->create($category, $productImport->rowErrors()->get()->pluck('values'));

        return response()->download($file['path'], 'product-import-'.$productImport->id.'-errors.xlsx', ['Content-Type' => ProductExportService::CONTENT_TYPE])->deleteFileAfterSend();
    }

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
                'category_id' => $request->validated('category_id'),
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
