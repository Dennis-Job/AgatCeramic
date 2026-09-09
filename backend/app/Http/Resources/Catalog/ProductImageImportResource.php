<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use App\Models\ProductImageImport;
use Illuminate\Http\Request;

/** @extends ApiResource<ProductImageImport> */
class ProductImageImportResource extends ApiResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'filename' => $this->original_filename, 'status' => $this->status, 'total_folders' => $this->total_folders, 'processed_folders' => $this->processed_folders, 'created_images' => $this->created_images, 'replaced_images' => $this->replaced_images, 'failed_folders' => $this->failed_folders, 'errors' => $this->errors->map(fn ($error) => ['sku' => $error->sku, 'entry' => $error->entry, 'messages' => $error->messages])->all(), 'has_error_file' => $this->failed_folders > 0 && in_array($this->status, ['completed', 'failed'], true), 'error_message' => $this->error_message, 'created_at' => $this->created_at?->toAtomString(), 'started_at' => $this->started_at?->toAtomString(), 'completed_at' => $this->completed_at?->toAtomString()];
    }
}
