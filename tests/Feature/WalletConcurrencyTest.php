<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

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

    public function test_BEFORE_double_spend_without_locking(): void
    {

        $wallet = Wallet::create([
            'user_id' => $this->user->id,
            'balance' => 100.00,
            'version' => 0,
        ]);

        $balanceA = (float) DB::table('wallets')->where('id', $wallet->id)->value('balance');

        $balanceB = (float) DB::table('wallets')->where('id', $wallet->id)->value('balance');

        $this->assertEquals(100.00, $balanceA);
        $this->assertEquals(100.00, $balanceB);

        DB::table('wallets')->where('id', $wallet->id)->update(['balance' => $balanceA - 80]);

        DB::table('wallets')->where('id', $wallet->id)->update(['balance' => $balanceB - 80]);

        $wallet->refresh();

        $this->assertEquals(20.00, (float) $wallet->balance);

    }

    public function test_AFTER_optimistic_locking_prevents_double_spend(): void
    {

        Wallet::create([
            'user_id' => $this->user->id,
            'balance' => 100.00,
            'version' => 0,
        ]);

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

        $affectedB = DB::table('wallets')
            ->where('id', $walletA->id)
            ->where('version', $walletA->version)
            ->where('balance', '>=', 80)
            ->update([
                'balance' => DB::raw('balance - 80'),
                'version' => DB::raw('version + 1'),
            ]);

        $this->assertEquals(0, $affectedB, 'Thread B FAILS — version mismatch (double-spend prevented).');

        $wallet = Wallet::where('user_id', $this->user->id)->first();
        $this->assertEquals(20.00, (float) $wallet->balance, 'Balance is $20 (only one debit succeeded).');
        $this->assertEquals(1, $wallet->version, 'Version incremented once.');
    }

    public function test_AFTER_credit_works(): void
    {
        $tx = $this->walletService->credit($this->user->id, 250.00, 'Initial deposit');

        $this->assertEquals('credit', $tx->type);
        $this->assertEquals(0.00, (float) $tx->balance_before);
        $this->assertEquals(250.00, (float) $tx->balance_after);

        $wallet = Wallet::where('user_id', $this->user->id)->first();
        $this->assertEquals(250.00, (float) $wallet->balance);
    }

    public function test_AFTER_debit_insufficient_funds_rejected(): void
    {
        $this->walletService->credit($this->user->id, 50.00);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient wallet balance');

        $this->walletService->debit($this->user->id, 100.00, 'Too much');
    }

    public function test_AFTER_audit_trail_complete(): void
    {
        $this->walletService->credit($this->user->id, 500.00, 'Deposit');
        $this->walletService->debit($this->user->id, 120.00, 'Purchase');
        $this->walletService->debit($this->user->id, 80.00, 'Another purchase');

        $wallet = Wallet::where('user_id', $this->user->id)->first();
        $this->assertEquals(300.00, (float) $wallet->balance);

        $transactions = $wallet->transactions;
        $this->assertCount(3, $transactions);

        $this->assertEquals(0.00, (float) $transactions[2]->balance_before);
        $this->assertEquals(500.00, (float) $transactions[2]->balance_after);
        $this->assertEquals(500.00, (float) $transactions[1]->balance_before);
        $this->assertEquals(380.00, (float) $transactions[1]->balance_after);
        $this->assertEquals(380.00, (float) $transactions[0]->balance_before);
        $this->assertEquals(300.00, (float) $transactions[0]->balance_after);
    }
}
