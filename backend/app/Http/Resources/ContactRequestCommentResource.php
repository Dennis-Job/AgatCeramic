<?php

namespace App\Http\Resources;

use App\Models\ContactRequestComment;
use Illuminate\Http\Request;

/** @extends ApiResource<ContactRequestComment> */
class ContactRequestCommentResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        $author = $this->author_snapshot ?? [];

        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => isset($author['name']) ? ['id' => $this->author_id, 'name' => $author['name']] : null,
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
