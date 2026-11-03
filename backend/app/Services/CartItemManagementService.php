<?php

namespace App\Services;

use App\Data\PricedCartItem;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartItemManagementService
{
    public function __construct(
        private readonly GuestCartLifetime $lifetime,
        private readonly MoneyCalculator $amountCalculator,
    ) {}

    /** @return list<PricedCartItem> */
    public function items(Cart $cart): array
    {
        return array_values($cart->items()->with('product.primaryImage')->get()->map($this->priceItem(...))->all());
    }

    /** @return array{item: PricedCartItem, created: bool} */
    public function add(Cart $cart, int $productId, int $quantity): array
    {
        return DB::transaction(function () use ($cart, $productId, $quantity): array {
            $cart = $this->lockUsableCart($cart);
            $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
            $this->assertAvailable($product, $quantity);
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                $item = CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ]);
                $this->lifetime->markActive($cart);

                return ['item' => $this->loadPricedItem($item), 'created' => true];
            }

            $newQuantity = $item->quantity + $quantity;
            $this->assertAvailable($product, $newQuantity);
            $item->update(['quantity' => $newQuantity]);
            $this->lifetime->markActive($cart);

            return ['item' => $this->loadPricedItem($item), 'created' => false];
        });
    }

    public function update(Cart $cart, CartItem $item, int $quantity): PricedCartItem
    {
        return DB::transaction(function () use ($cart, $item, $quantity): PricedCartItem {
            $cart = $this->lockUsableCart($cart);
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();
            $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
            $this->assertAvailable($product, $quantity, reportAvailableQuantity: true);
            $item->update(['quantity' => $quantity]);
            $this->lifetime->markActive($cart);

            return $this->loadPricedItem($item);
        });
    }

    public function delete(Cart $cart, CartItem $item): void
    {
        DB::transaction(function () use ($cart, $item): void {
            $cart = $this->lockUsableCart($cart);
            CartItem::query()
                ->where('cart_id', $cart->id)
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail()
                ->delete();

            if (CartItem::query()->where('cart_id', $cart->id)->exists()) {
                $this->lifetime->markActive($cart);
            } else {
                $this->lifetime->markEmpty($cart);
            }
        });
    }

    private function lockUsableCart(Cart $cart): Cart
    {
        return Cart::query()
            ->whereKey($cart->id)
            ->whereNull('checked_out_at')
            ->where('expires_at', '>', now())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertAvailable(Product $product, int $quantity, bool $reportAvailableQuantity = false): void
    {
        $canSell = $product->is_active && $product->is_on_sale
            && $product->category?->is_active === true && $product->price !== null;
        $availableQuantity = $canSell ? $product->stock_quantity : null;

        if ($availableQuantity !== null && $quantity <= min(9999, $availableQuantity)) {
            return;
        }

        $details = ['product_id' => ['Товар недоступен в запрошенном количестве.']];
        if ($reportAvailableQuantity && $availableQuantity !== null && $availableQuantity < $quantity) {
            $details['available_quantity'] = [(string) $availableQuantity];
        }

        throw ValidationException::withMessages($details);
    }

    private function loadPricedItem(CartItem $item): PricedCartItem
    {
        return $this->priceItem($item->load('product.primaryImage'));
    }

    private function priceItem(CartItem $item): PricedCartItem
    {
        $price = $item->product?->price;

        return new PricedCartItem(
            $item,
            $price,
            $price === null ? null : $this->amountCalculator->multiply($price, $item->quantity),
        );
    }
}
