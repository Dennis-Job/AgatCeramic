<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use App\Models\ProductImageImport;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/** @extends ApiResource<ProductImageImport> */
class ProductImageImportResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'filename' => $this->original_filename, 'status' => $this->status, 'total_folders' => $this->total_folders, 'processed_folders' => $this->processed_folders, 'created_images' => $this->created_images, 'replaced_images' => $this->replaced_images, 'failed_folders' => $this->failed_folders, 'errors' => $this->errors->map(fn ($error) => ['sku' => $error->sku, 'entry' => $error->entry, 'messages' => $error->messages])->all(), 'has_error_file' => $this->failed_folders > 0 && in_array($this->status, ['completed', 'failed'], true), 'error_message' => $this->error_message, 'created_at' => $this->dateValue($this->created_at), 'started_at' => $this->dateValue($this->started_at), 'completed_at' => $this->dateValue($this->completed_at)];
    }

    private function dateValue(mixed $value): mixed
    {
        return $value instanceof CarbonInterface ? $value->toAtomString() : $value;
    }
}
