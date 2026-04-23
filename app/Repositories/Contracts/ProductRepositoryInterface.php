<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id): ?Product;

    public function findBySlug(string $slug): ?Product;

    /**
     * Fetch a product WITH a row-level pessimistic lock (SELECT ... FOR UPDATE).
     * The caller MUST be inside a DB transaction.
     */
    public function lockForUpdate(int $id): ?Product;

    /**
     * Attempt an optimistic decrement: succeed only if stock_version matches
     * and stock >= $qty. Returns true on success, false on conflict.
     */
    public function optimisticDecrementStock(int $productId, int $qty, int $expectedVersion): bool;
}
