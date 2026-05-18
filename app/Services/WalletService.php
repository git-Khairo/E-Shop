<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    private const MAX_RETRIES = 5;

    public function getOrCreate(int $userId): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0.00, 'version' => 0]
        );
    }

    public function credit(int $userId, float $amount, string $description = '', ?string $reference = null, array $meta = []): WalletTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        return DB::transaction(function () use ($userId, $amount, $description, $reference, $meta) {
            $wallet = $this->getOrCreate($userId);

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

    public function getTransactions(int $userId, int $perPage = 20)
    {
        $wallet = $this->getOrCreate($userId);
        return $wallet->transactions()->paginate($perPage);
    }

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
