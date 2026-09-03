<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class ProductImportResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->original_filename,
            'status' => $this->status,
            'category_id' => $this->category_id,
            'total_rows' => $this->total_rows,
            'failed_rows' => $this->failed_rows,
            'row_errors' => $this->rowErrors->map(fn ($error) => [
                'row' => $error->row_number,
                'name' => $error->name,
                'messages' => $error->messages,
            ])->all(),
            'has_error_file' => $this->category_id !== null && $this->failed_rows > 0 && in_array($this->status, ['completed', 'failed'], true),
            'created_rows' => $this->created_rows,
            'updated_rows' => $this->updated_rows,
            'processed_rows' => $this->processed_rows,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toAtomString(),
            'started_at' => $this->started_at?->toAtomString(),
            'completed_at' => $this->completed_at?->toAtomString(),
        ];
    }
}
