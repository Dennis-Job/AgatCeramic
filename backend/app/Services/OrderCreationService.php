<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CheckoutIdempotencyKey;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** @phpstan-type OrderItemSnapshot array<string, mixed>&array{product_id: int, product_name: string, product_sku: ?string, unit_price: float, quantity: int, line_total: string} */
class OrderCreationService
{
    public function __construct(
        private readonly OrderAmountCalculator $amountCalculator,
        private readonly OrderConfirmationService $confirmationService,
        private readonly OrderNumberService $orderNumberService,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(Cart $cart, array $attributes, string $idempotencyKey): OrderCreationResult
    {
        $keyHash = hash_hmac('sha256', $idempotencyKey, $this->applicationKey());
        $requestHash = hash_hmac('sha256', json_encode($this->sorted($attributes), JSON_THROW_ON_ERROR), $this->applicationKey());

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(fn (): OrderCreationResult => $this->createInTransaction($cart, $attributes, $keyHash, $requestHash));
            } catch (QueryException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Order creation did not complete.');
    }

    /** @param array<string, mixed> $attributes */
    private function createInTransaction(Cart $cart, array $attributes, string $keyHash, string $requestHash): OrderCreationResult
    {
        $idempotency = CheckoutIdempotencyKey::query()->where('key_hash', $keyHash)->lockForUpdate()->first();
        if ($idempotency !== null) {
            if (now()->greaterThan(Carbon::parse($idempotency->expires_at))) {
                $idempotency->forceFill([
                    'request_hash' => $requestHash,
                    'order_id' => null,
                    'expires_at' => now()->addDay(),
                ])->save();
            } elseif (! hash_equals($idempotency->request_hash, $requestHash)) {
                throw new ConflictHttpException('Idempotency-Key уже использовался с другими данными заказа.');
            } elseif ($idempotency->order_id !== null) {
                return new OrderCreationResult($idempotency->order()->with('items')->firstOrFail(), true);
            }
        } else {
            $idempotency = CheckoutIdempotencyKey::query()->create([
                'key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'expires_at' => now()->addDay(),
            ]);
        }

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
            'total_amount' => $this->amountCalculator->sum($this->lineTotals($snapshots)),
        ]);
        $order->items()->createMany($snapshots);
        $cart->items()->delete();
        $this->confirmationService->queue($order);
        $idempotency->order()->associate($order);
        $idempotency->save();

        return new OrderCreationResult($order->load('items'), false);
    }

    /**
     * @param  EloquentCollection<int, CartItem>  $items
     * @param  EloquentCollection<int|string, Product>  $products
     * @return list<OrderItemSnapshot>
     */
    private function snapshots(EloquentCollection $items, EloquentCollection $products): array
    {
        return array_values($items->map(function (CartItem $item) use ($products): array {
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
                'line_total' => $this->amountCalculator->multiply(number_format($product->price, 2, '.', ''), $item->quantity),
            ];
        })->all());
    }

    /**
     * @param  list<OrderItemSnapshot>  $snapshots
     * @return list<string>
     */
    private function lineTotals(array $snapshots): array
    {
        $amounts = [];
        foreach ($snapshots as $snapshot) {
            $amounts[] = $snapshot['line_total'];
        }

        return $amounts;
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return array<array-key, mixed>
     */
    private function sorted(array $attributes): array
    {
        ksort($attributes);

        foreach ($attributes as $key => $value) {
            if (is_array($value)) {
                $attributes[$key] = $this->sorted($value);
            }
        }

        return $attributes;
    }

    private function applicationKey(): string
    {
        $key = config('app.key');
        if (! is_string($key) || $key === '') {
            throw new \LogicException('Application key is not configured.');
        }

        return $key;
    }
}
