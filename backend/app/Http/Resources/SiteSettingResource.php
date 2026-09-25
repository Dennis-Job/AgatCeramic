<?php

namespace App\Http\Resources;

use App\Models\SiteSetting;
use Illuminate\Http\Request;

/** @extends ApiResource<SiteSetting> */
class SiteSettingResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'operator_type' => $this->operator_type,
            'seller_name' => $this->seller_name,
            'entrepreneur_name' => $this->entrepreneur_name,
            'inn' => $this->inn,
            'ogrnip' => $this->ogrnip,
            'address' => $this->address,
            'phones' => $this->phones ?? [],
            'email' => $this->email,
            'bank_details' => $this->publish_bank_details ? [
                'name' => $this->bank_name,
                'bik' => $this->bank_bik,
                'account' => $this->bank_account,
                'correspondent_account' => $this->bank_correspondent_account,
            ] : null,
        ];
    }
}
