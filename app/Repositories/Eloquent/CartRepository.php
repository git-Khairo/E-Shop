<?php

namespace App\Repositories\Eloquent;

use App\Models\CartItem;
use App\Repositories\Contracts\CartRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CartRepository implements CartRepositoryInterface
{
    public function forUser(int $userId): Collection
    {
        return CartItem::with('product')->where('user_id', $userId)->get();
    }

    /**
     * Atomic UPSERT so two browser tabs hitting "Add to cart" simultaneously
     * can never create two rows for the same (user, product). The unique
     * index on (user_id, product_id) is our race-condition guard.
     */
    public function upsert(int $userId, int $productId, int $quantity, float $unitPrice): void
    {
        DB::transaction(function () use ($userId, $productId, $quantity, $unitPrice) {
            CartItem::updateOrCreate(
                ['user_id' => $userId, 'product_id' => $productId],
                [
                    'quantity'            => DB::raw("COALESCE(quantity, 0) + {$quantity}"),
                    'unit_price_snapshot' => $unitPrice,
                ]
            );
        });
    }

    /**
     * Idempotent quantity set. Wraps the write in a transaction for atomicity
     * and relies on the (user_id, product_id) unique index for consistency
     * under concurrent modification.
     */
    public function setQuantity(int $userId, int $productId, int $quantity, float $unitPrice): int
    {
        if ($quantity <= 0) {
            $this->remove($userId, $productId);
            return 0;
        }

        DB::transaction(function () use ($userId, $productId, $quantity, $unitPrice) {
            CartItem::updateOrCreate(
                ['user_id' => $userId, 'product_id' => $productId],
                [
                    'quantity'            => $quantity,
                    'unit_price_snapshot' => $unitPrice,
                ]
            );
        });

        return $quantity;
    }

    public function remove(int $userId, int $productId): void
    {
        CartItem::where('user_id', $userId)->where('product_id', $productId)->delete();
    }

    public function clear(int $userId): void
    {
        CartItem::where('user_id', $userId)->delete();
    }
}
