<?php

namespace App\Services\Retention;

use App\Models\ContactRequest;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ContactRequestDataDestructionService
{
    public function delete(ContactRequest $request): void
    {
        $requestId = (int) $request->id;
        $request->delete();

        foreach (['contact_request_comments', 'contact_request_status_histories'] as $table) {
            if (DB::table($table)->where('contact_request_id', $requestId)->exists()) {
                throw new RuntimeException('Contact request child reconciliation failed.');
            }
        }
    }
}
