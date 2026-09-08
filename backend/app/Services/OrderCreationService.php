<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCreationService
{
    public function __construct(
        private readonly OrderAmountCalculator $amountCalculator,
        private readonly OrderNumberService $orderNumberService,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(Cart $cart, array $attributes): Order
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(fn (): Order => $this->createInTransaction($cart, $attributes));
            } catch (QueryException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Order creation did not complete.');
    }

    /** @param array<string, mixed> $attributes */
    private function createInTransaction(Cart $cart, array $attributes): Order
    {
        $cart = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
        $items = CartItem::query()
            ->where('cart_id', $cart->id)
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => ['Корзина пуста.']]);
        }

        $products = Product::query()
            ->whereIn('id', $items->pluck('product_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $snapshots = $this->snapshots($items, $products);
        $order = Order::query()->create([
            ...$attributes,
            'order_number' => $this->orderNumberService->generate(),
            'status' => 'new',
            'payment_status' => PaymentStatus::NotPaid,
            'total_amount' => $this->amountCalculator->sum(array_column($snapshots, 'line_total')),
        ]);
        $order->items()->createMany($snapshots);
        $cart->items()->delete();

        return $order->load('items');
    }

    /**
     * @param  Collection<int, CartItem>  $items
     * @param  Collection<int, Product>  $products
     * @return list<array<string, int|string>>
     */
    private function snapshots(Collection $items, Collection $products): array
    {
        return $items->map(function (CartItem $item) use ($products): array {
            $product = $products->get($item->product_id);
            if ($product === null || ! $product->is_active || $product->price === null || $product->stock_quantity === null || $product->stock_quantity < $item->quantity) {
                throw ValidationException::withMessages([
                    'cart' => ['Корзина содержит недоступный товар или недостаточное количество.'],
                ]);
            }

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'unit_price' => $product->price,
                'quantity' => $item->quantity,
                'line_total' => $this->amountCalculator->multiply($product->price, $item->quantity),
            ];
        })->all();
    }
}
