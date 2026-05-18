<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockService
{
    private const MAX_OPTIMISTIC_RETRIES = 50;

    public function __construct(
        private readonly ProductRepositoryInterface $repo,
    ) {}

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

        $product->stock -= $qty;
        $product->stock_version += 1;
        $product->save();
    }

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
                return;
            }

            $sleepUs = random_int(100, 1000 * min($attempt, 5));
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
