<?php

namespace App\Services;

use App\Models\Customer;
use App\Support\RussianPhoneNumber;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class OrderNumberService
{
    /**
     * Allocate in the caller's checkout transaction so failures roll back the counter.
     *
     * @return array{customer_id: int, customer_order_sequence: int, order_number: string}
     */
    public function generate(string $phone): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Order number allocation requires a transaction.');
        }
        $phone = RussianPhoneNumber::normalize($phone)
            ?? throw new InvalidArgumentException('A valid customer phone is required.');

        // The conflict update locks existing customers too. Unlike INSERT IGNORE then
        // SELECT, this remains safe when retention concurrently clears the phone.
        Customer::query()->upsert([
            ['phone' => $phone, 'last_order_sequence' => 0],
        ], ['phone'], ['phone']);
        $customer = Customer::query()->where('phone', $phone)->lockForUpdate()->firstOrFail();
        $customer->last_order_sequence++;
        $customer->save();

        return [
            'customer_id' => (int) $customer->id,
            'customer_order_sequence' => $customer->last_order_sequence,
            'order_number' => $customer->id.'-'.$customer->last_order_sequence,
        ];
    }
}
