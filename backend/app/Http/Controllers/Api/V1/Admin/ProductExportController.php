<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ExportProductsRequest;
use App\Models\Product;
use App\Services\ProductExportService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductExportController extends Controller
{
    public function __construct(private readonly ProductExportService $exportService) {}

    public function __invoke(ExportProductsRequest $request): BinaryFileResponse
    {
        Gate::authorize('export', Product::class);

        $export = $this->exportService->create($request->validated());

        return response()->download($export['path'], $export['name'], [
            'Content-Type' => ProductExportService::CONTENT_TYPE,
        ])->deleteFileAfterSend();
    }
}
