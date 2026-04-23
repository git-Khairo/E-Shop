<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Product (canonical, concurrency-aware).
 *
 * Concurrency-critical fields:
 *   - `stock`         : available units
 *   - `stock_version` : monotonically increasing, used for Optimistic Locking.
 *     See {@see \App\Services\StockService::decrementOptimistic()}.
 */
class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'sku',
        'name',
        'slug',
        'description',
        'price',
        'image',
        'stock',
        'stock_version',
        'is_active',
        'categories_id',
        // legacy (kept for backward compatibility with older rows)
        'amount',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'stock'         => 'integer',
        'stock_version' => 'integer',
        'is_active'     => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(categories::class, 'categories_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
