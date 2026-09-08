<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartItemManagementService
{
    /** @return array{item: CartItem, created: bool} */
    public function add(Cart $cart, int $productId, int $quantity): array
    {
        return DB::transaction(function () use ($cart, $productId, $quantity): array {
            $cart = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
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

                return ['item' => $this->withProduct($item), 'created' => true];
            }

            $newQuantity = $item->quantity + $quantity;
            $this->assertAvailable($product, $newQuantity);
            $item->update(['quantity' => $newQuantity]);

            return ['item' => $this->withProduct($item), 'created' => false];
        });
    }

    public function update(Cart $cart, CartItem $item, int $quantity): CartItem
    {
        return DB::transaction(function () use ($cart, $item, $quantity): CartItem {
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();
            $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
            $this->assertAvailable($product, $quantity);
            $item->update(['quantity' => $quantity]);

            return $this->withProduct($item);
        });
    }

    public function delete(Cart $cart, CartItem $item): void
    {
        CartItem::query()
            ->where('cart_id', $cart->id)
            ->whereKey($item->id)
            ->firstOrFail()
            ->delete();
    }

    private function assertAvailable(Product $product, int $quantity): void
    {
        if ($quantity > 9999 || ! $product->is_active || $product->price === null || $product->stock_quantity === null || $product->stock_quantity < $quantity) {
            throw ValidationException::withMessages([
                'product_id' => ['Товар недоступен в запрошенном количестве.'],
            ]);
        }
    }

    private function withProduct(CartItem $item): CartItem
    {
        return $item->load('product.primaryImage');
    }
}
