<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * StockService — the critical section of the whole system.
 *
 * Offers TWO decrement strategies; the OrderService picks one per config:
 *
 *  1. decrementPessimistic()  -> SELECT ... FOR UPDATE (serializes writers)
 *  2. decrementOptimistic()   -> versioned UPDATE + bounded retry loop (scales better)
 *
 * Both are safe against:
 *   - Lost updates
 *   - Overselling (stock going negative)
 *   - Double-spending under concurrent checkout
 */
class StockService
{
    /** Max retry attempts for optimistic locking before giving up. */
    private const MAX_OPTIMISTIC_RETRIES = 5;

    public function __construct(
        private readonly ProductRepositoryInterface $repo,
    ) {}

    /**
     * PESSIMISTIC LOCKING — caller MUST be inside a DB transaction.
     *
     * Flow:
     *   BEGIN;
     *     SELECT * FROM products WHERE id = ? FOR UPDATE;   <-- row is locked
     *     -- validate stock
     *     UPDATE products SET stock = stock - ? WHERE id = ?;
     *   COMMIT;  <-- lock released
     *
     * Other transactions attempting to SELECT ... FOR UPDATE the same row will
     * BLOCK here until we commit/rollback. This guarantees serial execution of
     * the critical section per row — at the cost of concurrency.
     */
    public function decrementPessimistic(int $productId, int $qty): void
    {
        $this->assertInTransaction();

        $product = $this->repo->lockForUpdate($productId);

        if (! $product) {
            throw new InsufficientStockException($productId, $qty, 0);
        }

        if ($product->stock < $qty) {
            throw new InsufficientStockException($productId, $qty, $product->stock);
        }

        // Inside the lock, the write is trivially safe.
        $product->stock -= $qty;
        $product->stock_version += 1; // keep versions coherent even on pessimistic path
        $product->save();
    }

    /**
     * OPTIMISTIC LOCKING — NO row lock held.
     *
     * Flow (repeated up to MAX_OPTIMISTIC_RETRIES times):
     *   1. Read (stock, stock_version) without locking.
     *   2. Validate stock >= qty (fast fail on true out-of-stock).
     *   3. Attempt a conditional UPDATE:
     *        UPDATE products
     *        SET stock = stock - ?, stock_version = stock_version + 1
     *        WHERE id = ? AND stock_version = ? AND stock >= ?;
     *      If affected rows == 1 -> SUCCESS.
     *      If affected rows == 0 -> another transaction won the race; retry.
     *
     * Why this is the right choice for hot-path e-commerce:
     *   - No locks means no blocking queues in the DB.
     *   - Under low contention we succeed on attempt #1.
     *   - Under high contention (flash sale) we degrade gracefully by retrying
     *     a few times and then surfacing the conflict to the user.
     */
    public function decrementOptimistic(int $productId, int $qty): void
    {
        for ($attempt = 1; $attempt <= self::MAX_OPTIMISTIC_RETRIES; $attempt++) {
            $product = $this->repo->findById($productId);

            if (! $product) {
                throw new InsufficientStockException($productId, $qty, 0);
            }
            if ($product->stock < $qty) {
                throw new InsufficientStockException($productId, $qty, $product->stock);
            }

            $ok = $this->repo->optimisticDecrementStock(
                productId:       $productId,
                qty:             $qty,
                expectedVersion: (int) $product->stock_version,
            );

            if ($ok) {
                return; // WON the race
            }

            // LOST the race — exponential backoff with jitter to avoid thundering-herd retries.
            $sleepUs = (int) ((2 ** $attempt) * 1000 + random_int(0, 1000));
            usleep($sleepUs);

            Log::debug('Optimistic lock retry', [
                'product_id' => $productId,
                'attempt'    => $attempt,
            ]);
        }

        throw new \RuntimeException(
            "Could not acquire optimistic lock on product #{$productId} after ".self::MAX_OPTIMISTIC_RETRIES.' retries.'
        );
    }

    /**
     * Used by the "return to shelf" flow (order cancelled, payment failed).
     * Always safe to add — no conflict possible.
     */
    public function increment(int $productId, int $qty): void
    {
        DB::table('products')
            ->where('id', $productId)
            ->update([
                'stock'         => DB::raw("stock + {$qty}"),
                'stock_version' => DB::raw('stock_version + 1'),
                'updated_at'    => now(),
            ]);
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('decrementPessimistic must be called inside a DB transaction.');
        }
    }
}
