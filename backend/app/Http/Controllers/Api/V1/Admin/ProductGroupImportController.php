<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreProductGroupImportRequest;
use App\Http\Resources\Catalog\ProductImportResource;
use App\Models\Product;
use App\Models\ProductImport;
use App\Services\ImportSubmissionService;
use App\Services\ProductExportService;
use App\Services\ProductGroupImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductGroupImportController extends Controller
{
    private function authorize(): void
    {
        Gate::authorize('import', Product::class);
        Gate::authorize('create', Product::class);
    }

    public function template(ProductGroupImportService $service): BinaryFileResponse
    {
        $this->authorize();
        $file = $service->createTemplate();

        return response()->download($file['path'], $file['name'], ['Content-Type' => ProductExportService::CONTENT_TYPE])->deleteFileAfterSend();
    }

    public function store(StoreProductGroupImportRequest $request, ImportSubmissionService $submissionService): JsonResponse
    {
        $this->authorize();
        $import = $submissionService->submitProductWorkbook(
            $this->authenticatedAdmin($request),
            $this->uploadedFile($request, 'file'),
            'product-group-imports',
            operation: 'group',
        );

        return (new ProductImportResource($import))->response()->setStatusCode(202);
    }

    public function show(Request $request, ProductImport $productImport): ProductImportResource
    {
        $this->authorize();
        abort_unless($productImport->operation === 'group' && $productImport->user_id === $this->authenticatedAdmin($request)->id, 404);

        return new ProductImportResource($productImport);
    }

    public function errors(Request $request, ProductImport $productImport, ProductGroupImportService $service): BinaryFileResponse
    {
        $this->authorize();
        abort_unless($productImport->operation === 'group' && $productImport->user_id === $this->authenticatedAdmin($request)->id && $productImport->failed_rows > 0 && in_array($productImport->status, ['completed', 'failed'], true), 404);
        $file = $service->createErrorReport($productImport);

        return response()->download($file['path'], $file['name'], ['Content-Type' => ProductExportService::CONTENT_TYPE])->deleteFileAfterSend();
    }
}
