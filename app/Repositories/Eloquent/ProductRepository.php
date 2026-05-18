<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductRepository implements ProductRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $q = Product::query()->where('is_active', true);

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $q->where(function ($sub) use ($term) {
                $sub->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if (! empty($filters['category_id'])) {
            $q->where('categories_id', $filters['category_id']);
        }

        if (isset($filters['price_min'])) {
            $q->where('price', '>=', (float) $filters['price_min']);
        }

        if (isset($filters['price_max'])) {
            $q->where('price', '<=', (float) $filters['price_max']);
        }

        $sort = $filters['sort'] ?? 'newest';
        match ($sort) {
            'price_asc'  => $q->orderBy('price', 'asc'),
            'price_desc' => $q->orderBy('price', 'desc'),
            'name'       => $q->orderBy('name', 'asc'),
            default      => $q->orderBy('id', 'desc'),
        };

        return $q->paginate($perPage)->withQueryString();
    }

    public function findById(int $id): ?Product
    {
        return Product::query()->find($id);
    }

    public function findBySlug(string $slug): ?Product
    {
        return Product::query()->where('slug', $slug)->first();
    }

    public function lockForUpdate(int $id): ?Product
    {
        return Product::query()->whereKey($id)->lockForUpdate()->first();
    }

    public function optimisticDecrementStock(int $productId, int $qty, int $expectedVersion): bool
    {
        $affected = DB::table('products')
            ->where('id', $productId)
            ->where('stock_version', $expectedVersion)
            ->where('stock', '>=', $qty)
            ->update([
                'stock'         => DB::raw("stock - {$qty}"),
                'stock_version' => DB::raw('stock_version + 1'),
                'updated_at'    => now(),
            ]);

        return $affected === 1;
    }
}
