<?php

namespace App\Http\Resources;

use App\Models\ContactRequestComment;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/** @extends ApiResource<ContactRequestComment> */
class ContactRequestCommentResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        $author = $this->getAttribute('author_snapshot');

        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => is_array($author) && isset($author['name']) && is_string($author['name']) ? ['id' => $this->author_id, 'name' => $author['name']] : null,
            'created_at' => $this->dateValue($this->created_at),
        ];
    }

    private function dateValue(mixed $value): mixed
    {
        return $value instanceof CarbonInterface ? $value->toAtomString() : $value;
    }
}
