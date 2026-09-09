<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreProductPriceStatusImportRequest;
use App\Http\Resources\Catalog\ProductImportResource;
use App\Models\Product;
use App\Models\ProductImport;
use App\Services\ImportSubmissionService;
use App\Services\ProductExportService;
use App\Services\ProductPriceStatusImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductPriceStatusImportController extends Controller
{
    public function template(ProductPriceStatusImportService $service): BinaryFileResponse
    {
        Gate::authorize('import', Product::class);
        $file = $service->createTemplate();

        return response()->download($file['path'], $file['name'], ['Content-Type' => ProductExportService::CONTENT_TYPE])->deleteFileAfterSend();
    }

    public function store(StoreProductPriceStatusImportRequest $request, ImportSubmissionService $submissionService): JsonResponse
    {
        Gate::authorize('import', Product::class);
        $import = $submissionService->submitProductWorkbook(
            $this->authenticatedAdmin($request),
            $request->file('file'),
            'product-price-status-imports',
            operation: 'price_status',
        );

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
