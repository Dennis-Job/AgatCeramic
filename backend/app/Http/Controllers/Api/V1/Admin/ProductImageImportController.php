<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreProductImageImportRequest;
use App\Http\Resources\Catalog\ProductImageImportResource;
use App\Models\Product;
use App\Models\ProductImageImport;
use App\Services\ImportSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImageImportController extends Controller
{
    public function store(StoreProductImageImportRequest $request, ImportSubmissionService $submissionService): JsonResponse
    {
        Gate::authorize('import', Product::class);
        $import = $submissionService->submitProductImageArchive(
            $this->authenticatedAdmin($request),
            $request->file('file'),
        );

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
