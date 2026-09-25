<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AdminSiteSettingResource extends SiteSettingResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'bank_name' => $this->bank_name,
            'bank_bik' => $this->bank_bik,
            'bank_account' => $this->bank_account,
            'bank_correspondent_account' => $this->bank_correspondent_account,
            'publish_bank_details' => $this->publish_bank_details,
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
