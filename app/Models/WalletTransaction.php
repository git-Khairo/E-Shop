<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WalletTransaction — immutable ledger entry.
 *
 * Every wallet operation (credit, debit, transfer, refund) creates a
 * transaction record. This provides a full audit trail and enables
 * reconciliation: sum(credits) - sum(debits) MUST equal current balance.
 */
class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'user_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'reference',
        'description',
        'meta',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after'  => 'decimal:2',
        'meta'           => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
