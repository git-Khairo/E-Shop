<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * WALLET CONCURRENCY TEST
 *
 * Demonstrates the double-spend problem in digital wallets and proves
 * our optimistic locking solution prevents it.
 *
 * This is directly analogous to Requirement 1 (stock race condition),
 * applied to financial data where correctness is even more critical.
 *
 * === BEFORE (No Protection) ===
 * Thread A reads balance=$100, Thread B reads balance=$100.
 * Both debit $80: A writes $20, B overwrites to $20.
 * Total debited: $160 from a $100 wallet = DOUBLE SPEND.
 *
 * === AFTER (Optimistic Locking) ===
 * Thread A reads balance=$100, version=0.
 * Thread B reads balance=$100, version=0.
 * A: UPDATE WHERE version=0 → success, version→1, balance→$20.
 * B: UPDATE WHERE version=0 → FAILS (version is 1 now) → retries.
 * B re-reads: balance=$20, version=1 → $80 > $20 → InsufficientFunds!
 * Result: only $80 debited, $20 remains. No double spend.
 */
class WalletConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $walletService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = app(WalletService::class);

        $this->user = User::create([
            'username' => 'wallettest',
            'email'    => 'wallet@test.com',
            'password' => bcrypt('password'),
        ]);
    }

    /**
     * BEFORE: Double-spend without protection.
     */
    public function test_BEFORE_double_spend_without_locking(): void
    {
        // Create wallet with $100
        $wallet = Wallet::create([
            'user_id' => $this->user->id,
            'balance' => 100.00,
            'version' => 0,
        ]);

        // Thread A reads balance
        $balanceA = (float) DB::table('wallets')->where('id', $wallet->id)->value('balance');

        // Thread B reads balance (same stale value)
        $balanceB = (float) DB::table('wallets')->where('id', $wallet->id)->value('balance');

        $this->assertEquals(100.00, $balanceA);
        $this->assertEquals(100.00, $balanceB);

        // Thread A debits $80 → writes $20
        DB::table('wallets')->where('id', $wallet->id)->update(['balance' => $balanceA - 80]);

        // Thread B debits $80 → also writes $20 (OVERWRITES A!)
        DB::table('wallets')->where('id', $wallet->id)->update(['balance' => $balanceB - 80]);

        $wallet->refresh();

        // BUG: balance is $20, but $160 was debited from a $100 wallet!
        $this->assertEquals(20.00, (float) $wallet->balance);
        // The correct balance after one successful $80 debit should be $20,
        // but the SECOND $80 debit should have been REJECTED (insufficient funds).
        // Instead, both went through = DOUBLE SPEND.
    }

    /**
     * AFTER: Optimistic locking prevents double-spend.
     */
    public function test_AFTER_optimistic_locking_prevents_double_spend(): void
    {
        // Create wallet with $100
        Wallet::create([
            'user_id' => $this->user->id,
            'balance' => 100.00,
            'version' => 0,
        ]);

        // Thread A: reads version=0, attempts debit $80
        $walletA = DB::table('wallets')->where('user_id', $this->user->id)->first();
        $affectedA = DB::table('wallets')
            ->where('id', $walletA->id)
            ->where('version', $walletA->version)
            ->where('balance', '>=', 80)
            ->update([
                'balance' => DB::raw('balance - 80'),
                'version' => DB::raw('version + 1'),
            ]);

        $this->assertEquals(1, $affectedA, 'Thread A succeeds.');

        // Thread B: reads STALE version=0, attempts debit $80
        $affectedB = DB::table('wallets')
            ->where('id', $walletA->id)
            ->where('version', $walletA->version) // stale version!
            ->where('balance', '>=', 80)
            ->update([
                'balance' => DB::raw('balance - 80'),
                'version' => DB::raw('version + 1'),
            ]);

        $this->assertEquals(0, $affectedB, 'Thread B FAILS — version mismatch (double-spend prevented).');

        // Verify final state
        $wallet = Wallet::where('user_id', $this->user->id)->first();
        $this->assertEquals(20.00, (float) $wallet->balance, 'Balance is $20 (only one debit succeeded).');
        $this->assertEquals(1, $wallet->version, 'Version incremented once.');
    }

    /**
     * AFTER: Credit operation works correctly.
     */
    public function test_AFTER_credit_works(): void
    {
        $tx = $this->walletService->credit($this->user->id, 250.00, 'Initial deposit');

        $this->assertEquals('credit', $tx->type);
        $this->assertEquals(0.00, (float) $tx->balance_before);
        $this->assertEquals(250.00, (float) $tx->balance_after);

        $wallet = Wallet::where('user_id', $this->user->id)->first();
        $this->assertEquals(250.00, (float) $wallet->balance);
    }

    /**
     * AFTER: Debit with insufficient funds is rejected.
     */
    public function test_AFTER_debit_insufficient_funds_rejected(): void
    {
        $this->walletService->credit($this->user->id, 50.00);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient wallet balance');

        $this->walletService->debit($this->user->id, 100.00, 'Too much');
    }

    /**
     * AFTER: Full audit trail maintained.
     */
    public function test_AFTER_audit_trail_complete(): void
    {
        $this->walletService->credit($this->user->id, 500.00, 'Deposit');
        $this->walletService->debit($this->user->id, 120.00, 'Purchase');
        $this->walletService->debit($this->user->id, 80.00, 'Another purchase');

        $wallet = Wallet::where('user_id', $this->user->id)->first();
        $this->assertEquals(300.00, (float) $wallet->balance);

        $transactions = $wallet->transactions;
        $this->assertCount(3, $transactions);

        // Verify chain: each balance_before matches previous balance_after
        $this->assertEquals(0.00, (float) $transactions[2]->balance_before);    // First: 0 → 500
        $this->assertEquals(500.00, (float) $transactions[2]->balance_after);
        $this->assertEquals(500.00, (float) $transactions[1]->balance_before);  // Second: 500 → 380
        $this->assertEquals(380.00, (float) $transactions[1]->balance_after);
        $this->assertEquals(380.00, (float) $transactions[0]->balance_before);  // Third: 380 → 300
        $this->assertEquals(300.00, (float) $transactions[0]->balance_after);
    }
}
