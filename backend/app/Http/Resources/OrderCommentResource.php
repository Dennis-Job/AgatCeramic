<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class OrderCommentResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $author = $this->author_snapshot ?? [];

        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => isset($author['name']) ? [
                'id' => $this->author_id,
                'name' => $author['name'],
            ] : null,
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
