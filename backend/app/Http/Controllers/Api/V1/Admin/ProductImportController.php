<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ImportProductsRequest;
use App\Http\Requests\Api\V1\Admin\ProductImportTemplateRequest;
use App\Http\Resources\Catalog\ProductImportResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Services\ImportSubmissionService;
use App\Services\ProductExportService;
use App\Services\ProductImportErrorReportService;
use App\Services\ProductImportTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductImportController extends Controller
{
    public function template(ProductImportTemplateRequest $request, ProductImportTemplateService $service): BinaryFileResponse
    {
        Gate::authorize('import', Product::class);
        $category = Category::query()->findOrFail($request->integer('category_id'));
        $editing = $request->boolean('editing');
        $file = $service->create($category, $editing ? $service->editingRows($category) : [], $editing);

        return response()->download($file['path'], $file['name'], ['Content-Type' => ProductExportService::CONTENT_TYPE])->deleteFileAfterSend();
    }

    public function errors(Request $request, ProductImport $productImport, ProductImportTemplateService $service, ProductImportErrorReportService $errorReportService): BinaryFileResponse
    {
        Gate::authorize('import', Product::class);
        abort_unless($productImport->user_id === $request->user()->id, 404);
        abort_unless(in_array($productImport->status, ['completed', 'failed'], true) && $productImport->failed_rows > 0, 404);
        $file = $productImport->category_id === null
            ? $errorReportService->create($productImport)
            : $service->create(
                Category::query()->findOrFail($productImport->category_id),
                $productImport->rowErrors()->get()->pluck('values'),
                $productImport->rowErrors()->first()?->values !== null && array_key_exists('sku', $productImport->rowErrors()->first()->values),
            );

        return response()->download($file['path'], $file['name'] ?? 'product-import-'.$productImport->id.'-errors.xlsx', ['Content-Type' => ProductExportService::CONTENT_TYPE])->deleteFileAfterSend();
    }

    public function store(ImportProductsRequest $request, ImportSubmissionService $submissionService): JsonResponse
    {
        Gate::authorize('import', Product::class);

        $import = $submissionService->submitProductWorkbook(
            $this->authenticatedAdmin($request),
            $request->file('file'),
            'product-imports',
            $request->validated('category_id'),
        );

        return (new ProductImportResource($import))->response()->setStatusCode(202);
    }

    public function show(Request $request, ProductImport $productImport): ProductImportResource
    {
        Gate::authorize('import', Product::class);
        abort_unless($productImport->user_id === $request->user()->id, 404);

        return new ProductImportResource($productImport);
    }
}
