<?php

namespace App\Http\Requests\Api\V1\Admin;

class UpdateSiteAppearanceRequest extends UpdateHomePageRequest
{
    /** @return array<string, list<mixed>> */
    #[\Override]
    public function rules(): array
    {
        return array_filter(parent::rules(), static fn (string $field): bool => $field === 'header' || $field === 'footer' || str_starts_with($field, 'header.') || str_starts_with($field, 'footer.'), ARRAY_FILTER_USE_KEY);
    }
}
