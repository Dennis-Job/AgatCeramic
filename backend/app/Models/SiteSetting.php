<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['operator_type', 'seller_name', 'entrepreneur_name', 'inn', 'ogrnip', 'address', 'phones', 'email', 'bank_name', 'bank_bik', 'bank_account', 'bank_correspondent_account', 'publish_bank_details'])]
class SiteSetting extends Model
{
    public $incrementing = false;

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['phones' => 'array', 'publish_bank_details' => 'boolean'];
    }
}
