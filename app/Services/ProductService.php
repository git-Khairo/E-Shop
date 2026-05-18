<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ProductService
{
    private const CACHE_TTL      = 300;
    private const CACHE_TAG_ALL  = 'products';
    private const CACHE_LIST_KEY = 'products:list:';
    private const CACHE_ITEM_KEY = 'products:item:';

    public function __construct(
        private readonly ProductRepositoryInterface $repo,
    ) {}

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
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

    public function invalidateCache(?int $productId = null): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::CACHE_TAG_ALL])->flush();
            return;
        }

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
