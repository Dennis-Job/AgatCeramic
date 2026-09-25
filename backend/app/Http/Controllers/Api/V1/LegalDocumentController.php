<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LegalDocumentVersionResource;
use App\Models\LegalDocumentVersion;

class LegalDocumentController extends Controller
{
    public function show(string $type): LegalDocumentVersionResource
    {
        abort_unless(in_array($type, ['offer', 'privacy_policy', 'consent'], true), 404);

        return new LegalDocumentVersionResource(
            LegalDocumentVersion::query()->where('type', $type)->whereNotNull('published_at')
                ->orderByDesc('published_at')->orderByDesc('id')->firstOrFail(),
        );
    }
}
