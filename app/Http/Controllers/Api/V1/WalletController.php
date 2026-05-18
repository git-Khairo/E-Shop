<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $wallet = $this->walletService->getOrCreate($request->user()->id);
        $wallet->load(['transactions' => fn($q) => $q->latest()->take(5)]);

        return response()->json([
            'wallet' => [
                'balance'              => $wallet->balance,
                'version'              => $wallet->version,
                'recent_transactions'  => $wallet->transactions,
            ],
        ]);
    }

    public function credit(Request $request): JsonResponse
    {
        $request->validate([
            'amount'      => 'required|numeric|min:0.01|max:100000',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $tx = $this->walletService->credit(
                $request->user()->id,
                (float) $request->amount,
                $request->description ?? 'Manual top-up',
            );

            return response()->json([
                'message'     => 'Wallet credited successfully.',
                'transaction' => $tx,
                'new_balance' => $tx->balance_after,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function debit(Request $request): JsonResponse
    {
        $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'reference'   => 'nullable|string|max:100',
        ]);

        try {
            $tx = $this->walletService->debit(
                $request->user()->id,
                (float) $request->amount,
                $request->description ?? 'Manual debit',
                $request->reference,
            );

            return response()->json([
                'message'     => 'Wallet debited successfully.',
                'transaction' => $tx,
                'new_balance' => $tx->balance_after,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function transfer(Request $request): JsonResponse
    {
        $request->validate([
            'to_user_id'  => 'required|integer|exists:users,id',
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $result = $this->walletService->transfer(
                $request->user()->id,
                (int) $request->to_user_id,
                (float) $request->amount,
                $request->description ?? '',
            );

            return response()->json([
                'message'     => 'Transfer completed successfully.',
                'debit'       => $result['debit'],
                'credit'      => $result['credit'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = $this->walletService->getTransactions(
            $request->user()->id,
            (int) $request->get('per_page', 20),
        );

        return response()->json($transactions);
    }
}
