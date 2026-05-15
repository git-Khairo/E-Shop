<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Wallet — each user has exactly one wallet.
 *
 * Concurrency-critical fields:
 *   - `balance` : current funds available
 *   - `version` : monotonically increasing, used for optimistic locking
 *     (same pattern as Product::stock_version — see StockService).
 */
class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'balance',
        'version',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'version' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->orderByDesc('id');
    }
}
