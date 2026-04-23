<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface CartRepositoryInterface
{
    /** @return Collection<int, \App\Models\CartItem> */
    public function forUser(int $userId): Collection;

    /**
     * Increment (or create) a cart line by {@see $quantity} for this user/product.
     * Used by "Add to cart" — adds delta to any existing quantity.
     */
    public function upsert(int $userId, int $productId, int $quantity, float $unitPrice): void;

    /**
     * Set the cart line quantity to an *exact* value. Idempotent by design —
     * safe against double-clicks and stale tab races. Passing 0 removes the row.
     *
     * @return int the final quantity that was stored (0 if the row was removed)
     */
    public function setQuantity(int $userId, int $productId, int $quantity, float $unitPrice): int;

    public function remove(int $userId, int $productId): void;

    public function clear(int $userId): void;
}
