<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * ProductService — read-heavy path is cached in Redis.
 *
 * Parallel programming concepts demonstrated:
 *   - Distributed cache (Redis) as a shared, low-latency read replica.
 *   - Cache-aside pattern with tag-based invalidation on writes.
 *   - Every writer (Order/Stock services) bumps `products_v{id}` tag so
 *     concurrent readers see fresh stock almost immediately.
 */
class ProductService
{
    private const CACHE_TTL      = 300;           // 5 minutes
    private const CACHE_TAG_ALL  = 'products';
    private const CACHE_LIST_KEY = 'products:list:';
    private const CACHE_ITEM_KEY = 'products:item:';

    public function __construct(
        private readonly ProductRepositoryInterface $repo,
    ) {}

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        // Cache the EXACT filtered+paginated response. Filters + page are part of the key.
        $cacheKey = self::CACHE_LIST_KEY.md5(json_encode([
            'f' => $filters,
            'p' => $perPage,
            'page' => request('page', 1),
        ]));

        return $this->rememberTagged($cacheKey, self::CACHE_TTL, function () use ($filters, $perPage) {
            return $this->repo->paginate($filters, $perPage);
        });
    }

    public function find(int $id): ?Product
    {
        $key = self::CACHE_ITEM_KEY.$id;

        return $this->rememberTagged($key, self::CACHE_TTL, function () use ($id) {
            return $this->repo->findById($id);
        });
    }

    /**
     * Must be called by any writer that changes a product (stock/price/name).
     * We flush the entire `products` tag — simple and safe. For very large catalogs
     * you could flush only the specific item key + list prefix via stores that support it.
     */
    public function invalidateCache(?int $productId = null): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::CACHE_TAG_ALL])->flush();
            return;
        }

        // Fallback for file/database cache stores (no tag support).
        if ($productId) {
            Cache::forget(self::CACHE_ITEM_KEY.$productId);
        }
    }

    private function rememberTagged(string $key, int $ttl, \Closure $cb): mixed
    {
        if ($this->supportsTags()) {
            return Cache::tags([self::CACHE_TAG_ALL])->remember($key, $ttl, $cb);
        }

        return Cache::remember($key, $ttl, $cb);
    }

    private function supportsTags(): bool
    {
        $store = config('cache.default');
        return in_array($store, ['redis', 'memcached'], true);
    }
}
