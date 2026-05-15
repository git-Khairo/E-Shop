<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WalletService — concurrency-safe digital wallet operations.
 *
 * Uses OPTIMISTIC LOCKING (same pattern as StockService) to prevent
 * double-spend on concurrent debit requests. This is critical for
 * a wallet because:
 *
 * RACE CONDITION (without protection):
 *   Thread A reads balance = $100
 *   Thread B reads balance = $100
 *   Thread A debits $80 → sets balance = $20 ✓
 *   Thread B debits $80 → sets balance = $20 ✗ (should be rejected! Only $20 left)
 *   Result: $160 spent from a $100 wallet = DOUBLE SPEND
 *
 * WITH OPTIMISTIC LOCKING:
 *   Thread A reads balance=$100, version=5
 *   Thread B reads balance=$100, version=5
 *   Thread A: UPDATE WHERE version=5 → succeeds, version→6
 *   Thread B: UPDATE WHERE version=5 → fails (version is now 6) → RETRY
 *   Thread B re-reads: balance=$20, version=6 → $80 > $20 → InsufficientFunds!
 *
 * Synchronization point: The WHERE version = ? clause in the UPDATE
 * is the atomic compare-and-swap that prevents lost updates.
 */
class WalletService
{
    private const MAX_RETRIES = 5;

    /**
     * Get or create a wallet for a user.
     */
    public function getOrCreate(int $userId): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0.00, 'version' => 0]
        );
    }

    /**
     * Credit (add funds) — always safe, no conflict possible on credit.
     * Still uses a transaction for atomicity of balance update + ledger entry.
     */
    public function credit(int $userId, float $amount, string $description = '', ?string $reference = null, array $meta = []): WalletTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        return DB::transaction(function () use ($userId, $amount, $description, $reference, $meta) {
            $wallet = $this->getOrCreate($userId);

            // Lock the row for the duration of this transaction (pessimistic here
            // because credits have zero contention risk, and we want exact balance_before).
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = $balanceBefore + $amount;

            $wallet->update([
                'balance' => $balanceAfter,
                'version' => $wallet->version + 1,
            ]);

            return WalletTransaction::create([
                'wallet_id'      => $wallet->id,
                'user_id'        => $userId,
                'type'           => 'credit',
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'reference'      => $reference,
                'description'    => $description ?: 'Wallet top-up',
                'meta'           => $meta,
            ]);
        });
    }

    /**
     * Debit (withdraw/spend funds) — OPTIMISTIC LOCKING with retry.
     *
     * This is the hot path for checkout. Under concurrent requests:
     *   1. Read current balance + version
     *   2. Validate balance >= amount
     *   3. Attempt conditional UPDATE (WHERE version = expected)
     *   4. If affected=0 → lost race → retry from step 1
     *   5. If affected=1 → success → record ledger entry
     */
    public function debit(int $userId, float $amount, string $description = '', ?string $reference = null, array $meta = []): WalletTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive.');
        }

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $wallet = $this->getOrCreate($userId);

            if ((float) $wallet->balance < $amount) {
                throw new \RuntimeException(
                    "Insufficient wallet balance. Available: {$wallet->balance}, Required: {$amount}"
                );
            }

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = $balanceBefore - $amount;

            // Atomic conditional update — the core of optimistic locking
            $affected = DB::table('wallets')
                ->where('id', $wallet->id)
                ->where('version', $wallet->version)
                ->where('balance', '>=', $amount)
                ->update([
                    'balance'    => $balanceAfter,
                    'version'    => DB::raw('version + 1'),
                    'updated_at' => now(),
                ]);

            if ($affected === 1) {
                // WON the race — record ledger entry
                return WalletTransaction::create([
                    'wallet_id'      => $wallet->id,
                    'user_id'        => $userId,
                    'type'           => 'debit',
                    'amount'         => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $balanceAfter,
                    'reference'      => $reference,
                    'description'    => $description ?: 'Wallet payment',
                    'meta'           => $meta,
                ]);
            }

            // LOST the race — backoff and retry
            $sleepUs = (int) ((2 ** $attempt) * 1000 + random_int(0, 1000));
            usleep($sleepUs);

            Log::debug('Wallet debit retry', [
                'user_id' => $userId,
                'attempt' => $attempt,
                'amount'  => $amount,
            ]);
        }

        throw new \RuntimeException(
            "Could not complete wallet debit after " . self::MAX_RETRIES . " retries (concurrent conflict)."
        );
    }

    /**
     * Refund — credits the wallet back (used when order is cancelled).
     */
    public function refund(int $userId, float $amount, string $orderReference, array $meta = []): WalletTransaction
    {
        return $this->credit(
            $userId,
            $amount,
            "Refund for order {$orderReference}",
            $orderReference,
            array_merge($meta, ['type' => 'refund'])
        );
    }

    /**
     * Transfer between two users — atomic debit + credit.
     */
    public function transfer(int $fromUserId, int $toUserId, float $amount, string $description = ''): array
    {
        if ($fromUserId === $toUserId) {
            throw new \InvalidArgumentException('Cannot transfer to yourself.');
        }

        $reference = 'TRF-' . strtoupper(bin2hex(random_bytes(6)));

        $debitTx = $this->debit($fromUserId, $amount, "Transfer out: {$description}", $reference);
        $creditTx = $this->credit($toUserId, $amount, "Transfer in: {$description}", $reference);

        return ['debit' => $debitTx, 'credit' => $creditTx];
    }

    /**
     * Get transaction history for a user's wallet.
     */
    public function getTransactions(int $userId, int $perPage = 20)
    {
        $wallet = $this->getOrCreate($userId);
        return $wallet->transactions()->paginate($perPage);
    }

    /**
     * Get wallet balance summary for monitoring dashboard.
     */
    public function getSystemStats(): array
    {
        return [
            'total_wallets'       => Wallet::count(),
            'total_balance'       => (float) Wallet::sum('balance'),
            'total_transactions'  => WalletTransaction::count(),
            'total_credits'       => (float) WalletTransaction::where('type', 'credit')->sum('amount'),
            'total_debits'        => (float) WalletTransaction::where('type', 'debit')->sum('amount'),
            'avg_balance'         => (float) Wallet::avg('balance'),
        ];
    }
}
